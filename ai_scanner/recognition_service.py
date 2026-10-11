"""Persistent localhost recognition worker for ProjectPulse.

Keeping OpenCV's detector and LBPH model in memory removes the Python startup
and model-loading work from every attendance scan.
"""

import argparse
import json
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import cv2
import numpy as np

from identify_face import LABELS_FILE, MODEL_FILE, cascade_classifier, model_is_current


MATCH_CONFIDENCE_LIMIT = 25.0
STRICTNESS_THRESHOLDS = {
    "very_strict": 20.0,
    "strict": MATCH_CONFIDENCE_LIMIT,
    "balanced": 35.0,
    "relaxed": 45.0,
    "very_relaxed": 55.0,
}
CONFIRMATION_SCANS = 3
CONFIRMATION_WINDOW_SECONDS = 4.5
CONFIRMATION_MISS_GRACE_SECONDS = 1.25


class RecognitionEngine:
    def __init__(self):
        self.confirmation_lock = threading.Lock()
        self.confirmations = {}
        self.confirmed = {}
        self.reload()

    def reload(self):
        if not hasattr(cv2, "face") or not MODEL_FILE.is_file() or not LABELS_FILE.is_file():
            raise RuntimeError("No trained model is available. Capture faces and train the recognizer first.")
        self.detector = cascade_classifier()
        self.recognizer = cv2.face.LBPHFaceRecognizer_create()
        self.recognizer.read(str(MODEL_FILE))
        self.labels = json.loads(LABELS_FILE.read_text(encoding="utf-8"))
        with self.confirmation_lock:
            self.confirmations.clear()
            self.confirmed.clear()

    def recognize(self, image_data, strictness="strict"):
        if not model_is_current():
            raise RuntimeError(
                "Learner face photos have changed since the model was trained. "
                "An administrator must train the face recognition model again."
            )
        confidence_limit = STRICTNESS_THRESHOLDS.get(strictness)
        if confidence_limit is None:
            raise ValueError("Invalid face recognition strictness level.")

        frame = cv2.imdecode(np.frombuffer(image_data, dtype=np.uint8), cv2.IMREAD_COLOR)
        if frame is None:
            raise ValueError("The camera sent an invalid image.")
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        faces = self.detector.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(80, 80))
        face_boxes = []
        candidates = {}
        for x, y, width, height in faces:
            face_box = {"x": int(x), "y": int(y), "width": int(width), "height": int(height)}
            label, confidence = self.recognizer.predict(cv2.resize(gray[y:y + height, x:x + width], (200, 200)))
            lrn = self.labels.get(str(label))
            if lrn and confidence <= confidence_limit:
                candidate = {
                    "lrn": lrn,
                    "confidence": round(float(confidence), 2),
                    "face_box": face_box,
                }
                previous = candidates.get(lrn)
                if previous is None or candidate["confidence"] < previous["confidence"]:
                    candidates[lrn] = candidate
                face_box["status"] = "confirming"
                face_box["lrn"] = lrn
            else:
                face_box["status"] = "unrecognized"
            face_boxes.append(face_box)

        with self.confirmation_lock:
            now = time.monotonic()
            for lrn, confirmation in list(self.confirmations.items()):
                if (
                    now - confirmation["started_at"] > CONFIRMATION_WINDOW_SECONDS
                    or now - confirmation["last_seen_at"] > CONFIRMATION_MISS_GRACE_SECONDS
                ):
                    del self.confirmations[lrn]
            for lrn, confirmation in list(self.confirmed.items()):
                if (
                    now > confirmation["expires_at"]
                    or now - confirmation["last_seen_at"] > CONFIRMATION_MISS_GRACE_SECONDS
                ):
                    del self.confirmed[lrn]

            matches = []
            pending = False
            for lrn, candidate in candidates.items():
                confirmed = self.confirmed.get(lrn)
                if confirmed is not None:
                    confirmed["expires_at"] = now + CONFIRMATION_WINDOW_SECONDS
                    confirmed["last_seen_at"] = now
                    confirmed["candidate"] = candidate
                    candidate["face_box"]["status"] = "recognized"
                    matches.append(candidate)
                    continue

                confirmation = self.confirmations.get(lrn)
                if (
                    confirmation is None
                    or now - confirmation["started_at"] > CONFIRMATION_WINDOW_SECONDS
                    or now - confirmation["last_seen_at"] > CONFIRMATION_MISS_GRACE_SECONDS
                ):
                    confirmation = {"count": 1, "started_at": now, "last_seen_at": now}
                    self.confirmations[lrn] = confirmation
                else:
                    confirmation["count"] += 1
                    confirmation["last_seen_at"] = now

                if confirmation["count"] >= CONFIRMATION_SCANS:
                    self.confirmed[lrn] = {
                        "expires_at": now + CONFIRMATION_WINDOW_SECONDS,
                        "last_seen_at": now,
                        "candidate": candidate,
                    }
                    del self.confirmations[lrn]
                    candidate["face_box"]["status"] = "recognized"
                    matches.append(candidate)
                else:
                    pending = True

            confirmed_lrns = {match["lrn"] for match in matches}
            for face_box in face_boxes:
                if face_box.get("lrn") in confirmed_lrns:
                    face_box["status"] = "recognized"

            return matches, face_boxes, pending


class Handler(BaseHTTPRequestHandler):
    engine = None

    def log_message(self, format, *args):
        return

    def send_json(self, status, payload):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if self.path == "/health":
            self.send_json(200, {
                "ok": True,
                "strictness_levels": list(STRICTNESS_THRESHOLDS),
            })
        else:
            self.send_json(404, {"ok": False, "error": "Not found."})

    def do_POST(self):
        if self.path == "/reload":
            try:
                self.engine.reload()
                self.send_json(200, {"ok": True})
            except Exception as exc:
                self.send_json(503, {"ok": False, "error": str(exc)})
            return
        if self.path != "/recognize":
            self.send_json(404, {"ok": False, "error": "Not found."})
            return
        length = int(self.headers.get("Content-Length", "0"))
        if length <= 0 or length > 5 * 1024 * 1024:
            self.send_json(422, {"ok": False, "error": "A camera frame of up to 5 MB is required."})
            return
        try:
            strictness = self.headers.get("X-Face-Strictness", "strict")
            matches, face_boxes, pending = self.engine.recognize(self.rfile.read(length), strictness)
            self.send_json(200, {
                "ok": True,
                "matches": matches,
                "faces": face_boxes,
                "face_count": len(face_boxes),
                "pending": pending,
            })
        except Exception as exc:
            self.send_json(422, {"ok": False, "error": str(exc)})


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--port", type=int, default=8765)
    args = parser.parse_args()
    Handler.engine = RecognitionEngine()
    ThreadingHTTPServer(("127.0.0.1", args.port), Handler).serve_forever()


if __name__ == "__main__":
    main()
