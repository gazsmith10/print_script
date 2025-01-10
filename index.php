<?php
session_start();

//Generates a CSRF token and stores it in the session.
function generateCsrfToken()
{
    if (!empty($_SESSION['csrf_token'])) {
        return $_SESSION['csrf_token'];
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

//Validates the CSRF token from the POST request against the session.
function validateCsrfToken($token)
{
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}


//Retrieves errors
function getUploadErrorMessage($errorCode)
{
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
    ];

    return $errorMessages[$errorCode] ?? 'Unknown upload error.';
}


//Validates the uploaded file for correct exstension and MIME type.
function validateFile(array $file)
{
    $allowedExtensions = ['csv' => 'text/csv'];
    $filename = $file['name'];
    $filetype = $file['type'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (!array_key_exists($ext, $allowedExtensions)) {
        return [false, 'Please upload a valid CSV file.'];
    }

    if (!in_array($filetype, $allowedExtensions, true)) {
        return [false, 'Please upload a valid CSV file.'];
    }

    return [true, ''];
}

//Sanitizes CSV headers by trimming whitespace and removing BOM.
function sanitizeHeaders(array $headers)
{
    return array_map(function ($header, $index) {
        $header = trim($header, '"'); 
        if ($index === 0) {
            $bom = "\xEF\xBB\xBF";
            if (substr($header, 0, 3) === $bom) {
                $header = substr($header, 3);
            }
        }
        return trim($header);
    }, $headers, array_keys($headers));
}

//Processes a row from CSV and returns the data or an error.
function processRow(array $row, array $sanitizedHeaders, array $numericFields)
{
    $data = array_combine($sanitizedHeaders, $row);

    if ($data === false) {
        return [false, 'Column mismatch in CSV row.'];
    }

    // ensure required fields exist
    $requiredFields = ['group_description', 'print_size_config_id', 'has_canvas_mask_safe_area'];
    
    $missingFields = array_filter($requiredFields, function($field) use ($data) {
        return !array_key_exists($field, $data);
    });

    if (!empty($missingFields)) {
        return [false, 'Missing required fields: ' . implode(', ', $missingFields) . '.'];
    }

    // Trrim and sanitize values
    foreach ($data as $key => $value) {
        $value = trim($value);

        // Handle NULL
        if (strtolower($value) === 'null' || $value === '') {
            if ($key === 'background_colour') {
                $data[$key] = "''";
            } else {
                $data[$key] = 'NULL';
            }
            continue; 
        }

        if (in_array($key, $numericFields, true)) {
            if (!is_numeric($value)) {
                $data[$key] = 'NULL';
            } else {
                $data[$key] = (int)$value;
            }
            continue; 
        }

        // Escape single quotes for the outputted SQL
        $data[$key] = "'" . addslashes($value) . "'";
    }

    return [true, $data];
}

//Handles the forced_orientation logic.
function handleForcedOrientation(array &$data)
{
    if (!isset($data['forced_orientation'])) {
        $data['forced_orientation'] = 'NULL';
        return [true, ''];
    }

    if ($data['forced_orientation'] === 0) {
        $data['forced_orientation'] = 'NULL';
        return [true, ''];
    }

    if ($data['forced_orientation'] === 1) {
        if (
            isset($data['width'], $data['height']) &&
            is_numeric($data['width']) &&
            is_numeric($data['height'])
        ) {
            if ($data['width'] > $data['height']) {
                $data['forced_orientation'] = "'landscape'";
            } else {
                $data['forced_orientation'] = "'portrait'";
            }
            return [true, ''];
        }

        // If width or height is missing or not number, set to NULL
        $data['forced_orientation'] = 'NULL';
        return [false, 'Width and Height must be numeric for forced_orientation calculation.'];
    }

    //Invalid value for forced_orientation
    $data['forced_orientation'] = 'NULL';
    return [false, "Invalid value for forced_orientation. Expected 0 or 1, found '{$data['forced_orientation']}'."];
}

// Generates SQL statements based on the processed data.
function generateSqlStatements(array $data, string &$sqlStatements)
{
    // Check if print_size_config_id is 0
    if ($data['print_size_config_id'] === 0) {
        $description = $data['group_description'];
        $width_mm = $data['width'] ?? 'NULL';
        $height_mm = $data['height'] ?? 'NULL';
        $final_dpi = $data['dpi'] ?? 'NULL';

        // Use INSERT IGNORE to prevent duplicate descriptions
        $sqlPrintSizeConfig = "INSERT IGNORE INTO `print_size_configs` (`description`, `width_mm`, `height_mm`, `final_dpi`, `created_at`, `updated_at`) VALUES ";
        $sqlPrintSizeConfig .= "({$description}, {$width_mm}, {$height_mm}, {$final_dpi}, NOW(), NOW());\n";

        $sqlStatements .= $sqlPrintSizeConfig;

        // Use LAST_INSERT_ID() to capture the newly inserted id only if a new row was inserted
        $sqlStatements .= "SET @new_print_size_config_id = LAST_INSERT_ID();\n";

        // Use the captured id in the printable_product_configs insert
        $printSizeConfigId = "@new_print_size_config_id";
    } else {
        // Use the existing print_size_config_id from the CSV
        $printSizeConfigId = $data['print_size_config_id'];
    }

    // Prepare values for printable_product_configs
    $data['print_size_config_id'] = $printSizeConfigId;

    // Generate INSERT statement for printable_product_configs
    $sqlPrintableProductConfig = "INSERT INTO `printable_product_configs` (`sku`, `print_size_config_id`, `total_prints`, `background_colour`, `print_background_colour`, `forced_orientation`, `has_canvas_mask_safe_area`, `canvas_mask`, `clipping_mask`, `overlay_mask`, `print_template_id`, `created_at`, `updated_at`, `deleted_at`) VALUES ";
    $sqlPrintableProductConfig .= "(";
    $sqlPrintableProductConfig .= "{$data['sku']}, ";
    $sqlPrintableProductConfig .= "{$data['print_size_config_id']}, ";
    $sqlPrintableProductConfig .= "{$data['total_prints']}, ";
    $sqlPrintableProductConfig .= "{$data['background_colour']}, ";
    $sqlPrintableProductConfig .= "{$data['print_background_colour']}, ";
    $sqlPrintableProductConfig .= "{$data['forced_orientation']}, ";
    $sqlPrintableProductConfig .= "{$data['has_canvas_mask_safe_area']}, ";
    $sqlPrintableProductConfig .= "{$data['canvas_mask']}, ";
    $sqlPrintableProductConfig .= "{$data['clipping_mask']}, ";
    $sqlPrintableProductConfig .= "{$data['overlay_mask']}, ";
    $sqlPrintableProductConfig .= "{$data['print_template_id']}, ";
    $sqlPrintableProductConfig .= "NOW(), NOW(), NULL";
    $sqlPrintableProductConfig .= ");\n";

    $sqlStatements .= $sqlPrintableProductConfig;
}

// Generate CSRF token for the form
$csrfToken = generateCsrfToken();

// Initialize variables for error messages and SQL statements
$errorMessages = [];
$sqlStatements = "";

// form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Validate CSRF token
    $csrfTokenPost = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfTokenPost)) {
        $errorMessages[] = 'Invalid CSRF token.';
    } else {
        // Check for errors
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $errorCode = $_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $errorMessages[] = getUploadErrorMessage($errorCode);
        } else {
            // Validate the uploaded file
            list($isValid, $validationMessage) = validateFile($_FILES['csv_file']);
            if (!$isValid) {
                $errorMessages[] = $validationMessage;
            } else {
                // Open the CSV file
                $csvFile = fopen($_FILES['csv_file']['tmp_name'], 'r');
                if ($csvFile === false) {
                    $errorMessages[] = 'Unable to open the uploaded CSV file.';
                } else {
                    // Read the header row
                    $headers = fgetcsv($csvFile, 1000, ",");
                    if ($headers === false) {
                        $errorMessages[] = 'CSV file is empty or invalid.';
                        fclose($csvFile);
                    } else {
                        // Sanitize 
                        $sanitizedHeaders = sanitizeHeaders($headers);

                        // Define which fields are numeric (no quotes in SQL))
                        $numericFields = [
                            'print_size_config_id',
                            'total_prints',
                            'width',
                            'height',
                            'dpi',
                            'print_template_id',
                            'forced_orientation'
                        ];

                        // go through each row
                        while (($row = fgetcsv($csvFile, 1000, ",")) !== false) {
                            list($success, $result) = processRow($row, $sanitizedHeaders, $numericFields);

                            if (!$success) {
                                $errorMessages[] = $result;
                                continue; 
                            }

                            $data = $result;

                            // Handle forced_orientation logic
                            list($orientationSuccess, $orientationMessage) = handleForcedOrientation($data);
                            if (!$orientationSuccess) {
                                $errorMessages[] = $orientationMessage;
                            }

                            // Generate SQL statements
                            generateSqlStatements($data, $sqlStatements);
                        }

                        fclose($csvFile);
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSV to SQL Uploader</title>
    <link rel="stylesheet" href="style.css"> 
</head>
<body>
    <h1>Upload CSV to Generate SQL Insert Statements</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        
        <label for="csv_file">Choose CSV File:</label>
        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
        <br><br>
        <button type="submit" name="submit">Upload and Generate SQL</button>
    </form>

    <?php if (!empty($errorMessages)): ?>
        <?php foreach ($errorMessages as $error): ?>
            <p class="error">Error: <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errorMessages) && !empty($sqlStatements)): ?>
        <h2>Generated SQL Statements:</h2>
        <textarea readonly><?php echo htmlspecialchars($sqlStatements, ENT_QUOTES, 'UTF-8'); ?></textarea>
    <?php endif; ?>
</body>
</html>
