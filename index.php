<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CSV to SQL Uploader</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background-color: #f9f9f9; 
        }
        h1 { color: #333; }
        form { 
            background-color: #fff; 
            padding: 20px; 
            border-radius: 5px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
            max-width: 600px; 
            margin-bottom: 20px; 
        }
        label { display: block; margin-bottom: 10px; }
        input[type="file"] { 
            padding: 5px; 
            border: 1px solid #ccc; 
            border-radius: 3px; 
            width: 100%; 
        }
        button { 
            padding: 10px 20px; 
            background-color: #28a745; 
            color: #fff; 
            border: none; 
            border-radius: 3px; 
            cursor: pointer; 
        }
        button:hover { background-color: #218838; }
        textarea { 
            width: 100%; 
            height: 400px; 
            padding: 10px; 
            border: 1px solid #ccc; 
            border-radius: 3px; 
            resize: vertical; 
            background-color: #fff; 
            font-family: monospace; 
            font-size: 14px; 
        }
        .error { color: red; }
        .success { color: green; }
        pre { 
            background-color: #f4f4f4; 
            padding: 10px; 
            border-radius: 3px; 
            overflow: auto; 
        }
        .download-link { 
            margin-top: 10px; 
            display: inline-block; 
            padding: 10px 15px; 
            background-color: #007bff; 
            color: #fff; 
            text-decoration: none; 
            border-radius: 3px; 
        }
        .download-link:hover { background-color: #0069d9; }
    </style>
</head>
<body>
    <h1>Upload CSV to Generate SQL Insert Statements</h1>
    <form action="" method="post" enctype="multipart/form-data">
        <?php
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        ?>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <label for="csv_file">Choose CSV File:</label>
        <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
        <br><br>
        <button type="submit" name="submit">Upload and Generate SQL</button>
    </form>

    <?php
    if (isset($_POST['submit'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            echo "<p class='error'>Error: Invalid CSRF token.</p>";
            exit;
        }

        // Check if a file was uploaded without errors
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $allowed = ['csv' => 'text/csv'];
            $filename = $_FILES['csv_file']['name'];
            $filetype = $_FILES['csv_file']['type'];
            $filesize = $_FILES['csv_file']['size'];

            // Verify file extension
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!array_key_exists($ext, $allowed)) {
                echo "<p class='error'>Error: Please upload a valid CSV file.</p>";
                exit;
            }
            if (in_array($filetype, $allowed)) {

                // Process the CSV file
                $csvFile = fopen($_FILES['csv_file']['tmp_name'], 'r');
                if ($csvFile !== FALSE) {
                    // Read the header row
                    $headers = fgetcsv($csvFile, 1000, ",");
                    if ($headers === FALSE) {
                        echo "<p class='error'>Error: CSV file is empty or invalid.</p>";
                        exit;
                    }

                    // Sanitizee
                    $sanitizedHeaders = array_map(function($header, $index) {
                        $header = trim($header, '"'); // Remove quotes
                        if ($index === 0) {
                            // Remove BOM from the first header if present
                            $bom = "\xEF\xBB\xBF";
                            if (substr($header, 0, 3) === $bom) {
                                $header = substr($header, 3);
                            }
                        }
                        return trim($header); // Remove any remaining whitespace
                    }, $headers, array_keys($headers));

                    // Define which fields are numeric (no quotes in SQL)
                    $numericFields = ['print_size_config_id', 'total_prints', 'width', 'height', 'dpi', 'print_template_id'];

                    // Initialize SQL statements
                    $sqlStatements = "";

                    // Iterate through each row
                    while (($row = fgetcsv($csvFile, 1000, ",")) !== FALSE) {
             
                        $data = array_combine($sanitizedHeaders, $row);

                        // Check if array_combine succeeded
                        if ($data === FALSE) {
                            echo "<p class='error'>Error: Column mismatch in CSV row. Expected " . count($sanitizedHeaders) . " columns, found " . count($row) . ".</p>";
                            // Uncomment for debugging to show the problamatic lines
                            /*
                            echo "<pre>Row Data:\n";
                            print_r($row);
                            echo "</pre>";
                            */
                            continue; // Skip this row and continue with the next 
                        }

                        // Ensure required fields exist
                        $requiredFields = ['sku', 'print_size_config_id', 'has_canvas_mask_safe_area'];
                        $missingFields = [];
                        foreach ($requiredFields as $field) {
                            if (!array_key_exists($field, $data)) {
                                $missingFields[] = $field;
                            }
                        }
                        if (!empty($missingFields)) {
                            echo "<p class='error'>Error: Missing required fields: " . implode(', ', $missingFields) . ".</p>";
                            continue; // Skip this row
                        }

                        foreach ($data as $key => $value) {
                            $value = trim($value);
                            // Handle NULL values
                            if (strtolower($value) === 'null' || $value === '') {
                                if ($key === 'background_colour') {
                                    // For background_colour, set to empty string if null or empty
                                    $data[$key] = "''";
                                } else {
                                    // For other fields, set to NULL
                                    $data[$key] = 'NULL';
                                }
                            } else {
                                if (in_array($key, $numericFields)) {
                                    // Ensure numeric fields are integers
                                    if (!is_numeric($value)) {
                                        echo "<p class='error'>Error: Field '$key' must be numeric. Found '$value'.</p>";
                                        $data[$key] = 'NULL';
                                    } else {
                                        $data[$key] = (int)$value;
                                    }
                                } else {
                                    // Escape single quotes for SQ L
                                    $data[$key] = "'" . addslashes($value) . "'";
                                }
                            }
                        }

                        // Check if print_size_config_id is 0
                        if ($data['print_size_config_id'] === 0) {
                        
                            $description = $data['sku'];
                            $width_mm = isset($data['width']) ? $data['width'] : 'NULL';
                            $height_mm = isset($data['height']) ? $data['height'] : 'NULL';
                            $final_dpi = isset($data['dpi']) ? $data['dpi'] : 'NULL';

                            $sqlPrintSizeConfig = "INSERT INTO `print_size_configs` (`description`, `width_mm`, `height_mm`, `final_dpi`, `created_at`, `updated_at`) VALUES ";
                            $sqlPrintSizeConfig .= "({$description}, {$width_mm}, {$height_mm}, {$final_dpi}, NOW(), NOW());\n";

                            $sqlStatements .= $sqlPrintSizeConfig;

                            // Use LAST_INSERT_ID() to capture the newly inserted id
                            $sqlStatements .= "SET @new_print_size_config_id = LAST_INSERT_ID();\n";

                            // Use the captured id in the printable_product_configs insert
                            $printSizeConfigId = "@new_print_size_config_id";
                        } else {
                            // Use the existing print_size_config_id from CSV
                            $printSizeConfigId = $data['print_size_config_id'];
                        }

                        // Prepare values for printable_product_configs
                        // Replace the print_size_config_id with the new one if it was 0
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

                    fclose($csvFile);

          
                    echo "<h2>Generated SQL Statements:</h2>";
                    echo "<textarea readonly>{$sqlStatements}</textarea>";

                
                } else {
                    echo "<p class='error'>Error: Unable to open the uploaded CSV file.</p>";
                }
            } else {
              
                $errorMessages = [
                    1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                    2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                    3 => 'The uploaded file was only partially uploaded.',
                    4 => 'No file was uploaded.',
                    6 => 'Missing a temporary folder.',
                    7 => 'Failed to write file to disk.',
                    8 => 'A PHP extension stopped the file upload.',
                ];

                $errorCode = $_FILES['csv_file']['error'];
                $errorMessage = isset($errorMessages[$errorCode]) ? $errorMessages[$errorCode] : 'Unknown upload error.';
                echo "<p class='error'>Error: " . htmlspecialchars($errorMessage) . "</p>";
            }
        }
    }
    ?>
</body>
</html>
