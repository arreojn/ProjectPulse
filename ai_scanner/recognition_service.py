"""Persistent localhost recognition worker for ProjectPulse.

Keeping OpenCV's detector and LBPH model in memory removes the Python startup
and model-loading work from every attendance scan.
"""

import argparse
import json
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import cv2
import numpy as np

from identify_face import LABELS_FILE, MODEL_FILE, cascade_classifier


class RecognitionEngine:
    def __init__(self):
        self.reload()

    def reload(self):
        if not hasattr(cv2, "face") or not MODEL_FILE.is_file() or not LABELS_FILE.is_file():
            raise RuntimeError("No trained model is available. Capture faces and train the recognizer first.")
        self.detector = cascade_classifier()
        self.recognizer = cv2.face.LBPHFaceRecognizer_create()
        self.recognizer.read(str(MODEL_FILE))
        self.labels = json.loads(LABELS_FILE.read_text(encoding="utf-8"))

    def recognize(self, image_data):
        frame = cv2.imdecode(np.frombuffer(image_data, dtype=np.uint8), cv2.IMREAD_COLOR)
        if frame is None:
            raise ValueError("The camera sent an invalid image.")
        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        faces = self.detector.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(80, 80))
        matches = []
        for x, y, width, height in faces:
            label, confidence = self.recognizer.predict(cv2.resize(gray[y:y + height, x:x + width], (200, 200)))
            lrn = self.labels.get(str(label))
            if lrn and confidence <= 70.0:
                matches.append({"lrn": lrn, "confidence": round(float(confidence), 2)})
        matches.sort(key=lambda match: match["confidence"])
        return matches, len(faces)


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
            self.send_json(200, {"ok": True})
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
            matches, face_count = self.engine.recognize(self.rfile.read(length))
            self.send_json(200, {"ok": True, "matches": matches, "face_count": face_count})
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
