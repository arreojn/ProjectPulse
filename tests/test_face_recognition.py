import threading
import unittest

import numpy as np

from ai_scanner import recognition_service


class ReduceFaceCandidatesTests(unittest.TestCase):
    def test_duplicate_overlapping_detections_for_one_face_are_collapsed(self):
        candidates = [
            {"lrn": "1001", "confidence": 14.5, "face_box": {"x": 10, "y": 20, "width": 80, "height": 80}},
            {"lrn": "1001", "confidence": 12.1, "face_box": {"x": 12, "y": 18, "width": 82, "height": 82}},
        ]

        reduced = recognition_service.reduce_face_candidates(candidates)

        self.assertEqual(len(reduced), 1)
        self.assertEqual(reduced[0]["lrn"], "1001")
        self.assertEqual(reduced[0]["confidence"], 12.1)

    def test_ambiguous_overlap_is_rejected(self):
        candidates = [
            {"lrn": "1001", "confidence": 12.0, "face_box": {"x": 10, "y": 10, "width": 100, "height": 100}},
            {"lrn": "2002", "confidence": 14.5, "face_box": {"x": 20, "y": 20, "width": 100, "height": 100}},
        ]

        reduced = recognition_service.reduce_face_candidates(candidates)

        self.assertEqual(reduced, [])

    def test_multiple_distinct_faces_remaining_in_frame_are_kept(self):
        candidates = [
            {"lrn": "1001", "confidence": 11.0, "face_box": {"x": 20, "y": 20, "width": 80, "height": 80}},
            {"lrn": "2002", "confidence": 13.5, "face_box": {"x": 260, "y": 30, "width": 80, "height": 80}},
        ]

        reduced = recognition_service.reduce_face_candidates(candidates)

        self.assertEqual({item["lrn"] for item in reduced}, {"1001", "2002"})


if __name__ == "__main__":
    import cv2

    unittest.main()
