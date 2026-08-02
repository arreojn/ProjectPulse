"""OpenCV LBPH recognition bridge for ProjectPulse facial attendance.

Learner registrations are named ``<12-digit-LRN>.jpg`` in
``assets/images/learners``.  PHP remains responsible for authentication and
attendance database writes; this process only recognises a face in a frame.
"""

import argparse
import json
from pathlib import Path

import cv2
import numpy as np


ROOT = Path(__file__).resolve().parent.parent
PHOTO_DIRECTORY = ROOT / "assets" / "images" / "learners"
MODEL_FILE = Path(__file__).resolve().parent / "recognizer.yml"
LABELS_FILE = Path(__file__).resolve().parent / "labels.json"
IMAGE_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}


def respond(**payload):
    print(json.dumps(payload))


def cascade_classifier():
    cascade_path = Path(cv2.data.haarcascades) / "haarcascade_frontalface_default.xml"
    cascade = cv2.CascadeClassifier(str(cascade_path))
    if cascade.empty():
        raise RuntimeError("OpenCV's frontal-face detector could not be loaded.")
    return cascade


def registration_files():
    if not PHOTO_DIRECTORY.is_dir():
        return []
    return [
        path for path in sorted(PHOTO_DIRECTORY.iterdir())
        if path.suffix.lower() in IMAGE_EXTENSIONS and path.stem.isdigit() and len(path.stem) == 12
    ]


def train_model():
    if not hasattr(cv2, "face"):
        raise RuntimeError("OpenCV contrib is required. Install ai_scanner/requirements.txt with Python 3.11.")

    detector = cascade_classifier()
    images, labels, label_map = [], [], {}
    for label, path in enumerate(registration_files()):
        image = cv2.imread(str(path), cv2.IMREAD_GRAYSCALE)
        if image is None:
            continue
        faces = detector.detectMultiScale(image, scaleFactor=1.1, minNeighbors=5, minSize=(80, 80))
        if len(faces) != 1:
            continue
        x, y, width, height = faces[0]
        images.append(cv2.resize(image[y:y + height, x:x + width], (200, 200)))
        labels.append(label)
        label_map[str(label)] = path.stem

    if not images:
        raise RuntimeError("No usable learner face registrations were found. Each photo must contain one clear face.")

    recognizer = cv2.face.LBPHFaceRecognizer_create()
    recognizer.train(images, np.array(labels, dtype=np.int32))
    recognizer.write(str(MODEL_FILE))
    LABELS_FILE.write_text(json.dumps(label_map), encoding="utf-8")
    return len(images)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--image", help="Path to a JPEG or PNG camera frame")
    parser.add_argument("--confidence", type=float, default=70.0, help="LBPH threshold; lower is stricter")
    parser.add_argument("--rebuild-cache", action="store_true")
    args = parser.parse_args()

    if args.rebuild_cache:
        respond(ok=True, registered=train_model())
        return
    if not args.image:
        respond(ok=False, error="A camera frame is required.")
        return
    if not MODEL_FILE.is_file() or not LABELS_FILE.is_file():
        respond(ok=False, error="No trained face model was found. Capture learner photos, then train the recognizer.")
        return
    if not hasattr(cv2, "face"):
        respond(ok=False, error="OpenCV contrib is required. Install ai_scanner/requirements.txt with Python 3.11.")
        return

    frame = cv2.imread(args.image)
    if frame is None:
        respond(ok=False, error="The camera sent an invalid image.")
        return
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    faces = cascade_classifier().detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(80, 80))
    if len(faces) == 0:
        respond(ok=True, recognized=False, message="No face detected. Centre one learner in the camera.")
        return

    recognizer = cv2.face.LBPHFaceRecognizer_create()
    recognizer.read(str(MODEL_FILE))
    labels = json.loads(LABELS_FILE.read_text(encoding="utf-8"))
    best_label, best_confidence = None, float("inf")
    for x, y, width, height in faces:
        label, confidence = recognizer.predict(cv2.resize(gray[y:y + height, x:x + width], (200, 200)))
        if confidence < best_confidence:
            best_label, best_confidence = label, float(confidence)

    lrn = labels.get(str(best_label))
    if lrn and best_confidence <= args.confidence:
        respond(ok=True, recognized=True, lrn=lrn, confidence=round(best_confidence, 2))
    else:
        respond(ok=True, recognized=False, message="Face not recognised.")


if __name__ == "__main__":
    try:
        main()
    except Exception as exc:
        respond(ok=False, error=f"Recognition service error: {exc}")
