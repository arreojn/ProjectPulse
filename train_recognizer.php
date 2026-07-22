<?php

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/helpers.php';

function train_face_recognizer(): array
{
    $outputLog = [];

    try {
        if (!extension_loaded('opencv')) {
            throw new RuntimeException('The php-opencv extension is not installed or enabled.');
        }

        // 1. Define paths
        $learnersPhotoPath = realpath(__DIR__ . '/assets/images/learners');
        $modelsPath = realpath(__DIR__ . '/opencv_models');
        $cascadePath = $modelsPath . '/haarcascade_frontalface_default.xml';
        $recognizerFile = $modelsPath . '/recognizer.yml';
        $labelsFile = $modelsPath . '/labels.json';

        if (!$learnersPhotoPath || !$modelsPath || !file_exists($cascadePath)) {
            throw new RuntimeException("Learner photos directory or OpenCV models directory/cascade file not found.");
        }

        // 2. Initialize
        $faceCascade = new cv\CascadeClassifier($cascadePath);
        $recognizer = cv\Face\LBPHFaceRecognizer::create();
        $trainingImages = [];
        $trainingLabels = [];
        $labelMap = [];
        $currentLabelId = 0;

        // 3. Get all learner images
        $imageFiles = new DirectoryIterator($learnersPhotoPath);
        $outputLog[] = "Processing learner images from: {$learnersPhotoPath}";

        foreach ($imageFiles as $fileInfo) {
            if ($fileInfo->isDot() || $fileInfo->isDir()) continue;

            $lrn = $fileInfo->getBasename('.' . $fileInfo->getExtension());
            if (!preg_match('/^\d{12}$/', $lrn)) {
                $outputLog[] = "Skipping invalid file name: " . $fileInfo->getFilename();
                continue;
            }

            $imagePath = $fileInfo->getPathname();
            $img = cv\imread($imagePath, cv\IMREAD_GRAYSCALE);

            $faces = [];
            $faceCascade->detectMultiScale($img, $faces);

            if (count($faces) !== 1) {
                $outputLog[] = "Warning: Found " . count($faces) . " faces in {$fileInfo->getFilename()}. Skipping. (Expected 1 face per photo).";
                continue;
            }

            $trainingImages[] = $img->getImageROI($faces[0]);
            $trainingLabels[] = $currentLabelId;
            $labelMap[$currentLabelId] = $lrn;
            
            $outputLog[] = "Processed: {$lrn}";
            $currentLabelId++;
        }

        if (empty($trainingImages)) {
            throw new RuntimeException("No valid training images found.");
        }

        $outputLog[] = "\nTraining recognizer with " . count($trainingImages) . " images...";
        $recognizer->train($trainingImages, $trainingLabels);

        $recognizer->write($recognizerFile);
        file_put_contents($labelsFile, json_encode($labelMap, JSON_PRETTY_PRINT));

        $outputLog[] = "\nTraining complete! Model and labels saved.";
        return ['success' => true, 'log' => $outputLog];
    } catch (Throwable $e) {
        $outputLog[] = "\nAn error occurred: " . $e->getMessage();
        return ['success' => false, 'log' => $outputLog, 'error' => $e->getMessage()];
    }
}

if (php_sapi_name() === 'cli') {
    echo "Starting face recognizer training...\n";
    $result = train_face_recognizer();
    echo implode("\n", $result['log']);
    echo "\n";
    exit($result['success'] ? 0 : 1);
}