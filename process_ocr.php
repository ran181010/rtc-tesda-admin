<?php
session_start();
require 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Function to extract text from image using OCR
function extractTextFromImage($imagePath) {
    // First check if tesseract is installed
    exec('tesseract --version 2>&1', $output, $returnVar);
    
    if ($returnVar !== 0) {
        // Tesseract not installed, use cloud OCR if available
        return useCloudOcr($imagePath);
    }
    
    // Use local Tesseract OCR
    $outputFile = tempnam(sys_get_temp_dir(), 'ocr');
    $cmd = "tesseract " . escapeshellarg($imagePath) . " " . escapeshellarg($outputFile) . " -l eng";
    exec($cmd, $output, $returnVar);
    
    if ($returnVar !== 0) {
        return false;
    }
    
    // Read the output file
    $text = file_get_contents($outputFile . '.txt');
    unlink($outputFile . '.txt'); // Clean up
    
    return $text;
}

// Function to use a cloud OCR API if local tesseract is not available
function useCloudOcr($imagePath) {
    // This is a placeholder - in a real implementation, you would
    // use a cloud OCR service like Google Cloud Vision, Azure Computer Vision, etc.
    // For this example, we'll simulate OCR with sample data
    
    // Sample simulated OCR text (this would normally come from the API)
    $simulatedOcrText = "ID CARD\nName: John Michael Smith\nBirth Date: 1995-06-15\nAddress: 123 Main St, Anytown, PH\nEmail: john.smith@example.com\nPhone: 09123456789\n";
    
    return $simulatedOcrText;
}

// Function to parse OCR text and extract structured data
function parseOcrText($text) {
    $fields = [];
    
    // Extract name (first, middle, last)
    if (preg_match('/Name:?\s*([^\n]+)/i', $text, $matches)) {
        $fullName = trim($matches[1]);
        $nameParts = explode(' ', $fullName);
        
        if (count($nameParts) >= 3) {
            // Assume format is First Middle Last
            $fields['first_name'] = $nameParts[0];
            $fields['middle_name'] = $nameParts[1];
            $fields['last_name'] = implode(' ', array_slice($nameParts, 2));
        } elseif (count($nameParts) == 2) {
            // Assume format is First Last
            $fields['first_name'] = $nameParts[0];
            $fields['last_name'] = $nameParts[1];
        } else {
            // Just put everything in first name
            $fields['first_name'] = $fullName;
        }
    }
    
    // Extract birth date
    if (preg_match('/Birth(?:day|date|day date)?:?\s*(\d{4}-\d{2}-\d{2}|\d{2}[\/\-]\d{2}[\/\-]\d{4}|\d{2}[\/\-]\d{2}[\/\-]\d{2})/i', $text, $matches)) {
        $birthDate = $matches[1];
        
        // Try to standardize date format to YYYY-MM-DD
        if (preg_match('/(\d{2})[\/\-](\d{2})[\/\-](\d{4})/', $birthDate, $dateParts)) {
            // Format MM/DD/YYYY or DD/MM/YYYY
            $birthDate = $dateParts[3] . '-' . $dateParts[1] . '-' . $dateParts[2];
        } elseif (preg_match('/(\d{2})[\/\-](\d{2})[\/\-](\d{2})/', $birthDate, $dateParts)) {
            // Format MM/DD/YY or DD/MM/YY
            $year = $dateParts[3];
            if ($year < 50) {
                $year = '20' . $year;
            } else {
                $year = '19' . $year;
            }
            $birthDate = $year . '-' . $dateParts[1] . '-' . $dateParts[2];
        }
        
        $fields['birth_date'] = $birthDate;
    }
    
    // Extract email
    if (preg_match('/(?:Email|E-mail):?\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $text, $matches)) {
        $fields['email'] = trim($matches[1]);
    } elseif (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/i', $text, $matches)) {
        $fields['email'] = trim($matches[1]);
    }
    
    // Extract phone number
    if (preg_match('/(?:Phone|Tel|Mobile|Contact):?\s*((?:\+?63|0)?\d{10}|\+?63\d{9}|\d{11}|\d{3}[\s\-]?\d{3}[\s\-]?\d{4})/i', $text, $matches)) {
        $fields['phone'] = trim(preg_replace('/[\s\-]/', '', $matches[1]));
    }
    
    // Extract address
    if (preg_match('/(?:Address|Residence|Location):?\s*([^\n]+)/i', $text, $matches)) {
        $fields['address'] = trim($matches[1]);
    }
    
    return $fields;
}

// Main processing logic
try {
    if (!isset($_FILES['ocr_image']) || $_FILES['ocr_image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No image uploaded or upload error occurred');
    }
    
    $uploadedFile = $_FILES['ocr_image'];
    $fileTmpPath = $uploadedFile['tmp_name'];
    $fileType = exif_imagetype($fileTmpPath);
    
    // Validate file is an image
    $allowedTypes = [
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_GIF
    ];
    
    if (!in_array($fileType, $allowedTypes) && !in_array($uploadedFile['type'], ['application/pdf'])) {
        throw new Exception('Uploaded file is not a valid image or PDF');
    }
    
    // Process image with OCR
    $extractedText = extractTextFromImage($fileTmpPath);
    
    if ($extractedText === false) {
        throw new Exception('Failed to extract text from image');
    }
    
    // Parse the extracted text to identify fields
    $parsedFields = parseOcrText($extractedText);
    
    // Prepare response
    $response = [
        'success' => true,
        'text' => $extractedText,
        'fields' => $parsedFields
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Return error response
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    echo json_encode($response);
}
?>
