<?php
$host = 'db.dev.ukpos.com';
$dbname = 'print';
$username = 'root';
$password = '!!Ro0Tpa5sw0rd""';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$templates = [
    ['label' => '20x30 - 0mm safe area', 'id' => '9:1', 'template_file_name' => '20x30_0mm_print_template.pdf'],
    ['label' => '20x30 - 10mm safe area', 'id' => '9:2', 'template_file_name' => '20x30_10mm_print_template.pdf'],
    ['label' => '20x30 - 20mm safe area', 'id' => '9:3', 'template_file_name' => '20x30_20mm_print_template.pdf'],
    ['label' => '20x30 - 30mm safe area', 'id' => '9:4', 'template_file_name' => '20x30_30mm_print_template.pdf'],
    ['label' => '30x40 - 0mm safe area', 'id' => '10:5', 'template_file_name' => '30x40_0mm_print_template.pdf'],
    ['label' => '30x40 - 10mm safe area', 'id' => '10:6', 'template_file_name' => '30x40_10mm_print_template.pdf'],
    ['label' => '30x40 - 20mm safe area', 'id' => '10:7', 'template_file_name' => '30x40_20mm_print_template.pdf'],
    ['label' => '30x40 - 30mm safe area', 'id' => '10:8', 'template_file_name' => '30x40_30mm_print_template.pdf'],
    ['label' => '40x60 - 0mm safe area', 'id' => '11:9', 'template_file_name' => '40x60_0mm_print_template.pdf'],
    ['label' => '40x60 - 10mm safe area', 'id' => '11:10', 'template_file_name' => '40x60_10mm_print_template.pdf'],
    ['label' => '40x60 - 20mm safe area', 'id' => '11:11', 'template_file_name' => '40x60_20mm_print_template.pdf'],
    ['label' => '40x60 - 30mm safe area', 'id' => '11:12', 'template_file_name' => '40x60_30mm_print_template.pdf'],
    ['label' => 'A0 - 0mm safe area', 'id' => '1:13', 'template_file_name' => 'A0_0mm_print_template.pdf'],
    ['label' => 'A0 - 10mm safe area', 'id' => '1:14', 'template_file_name' => 'A0_10mm_print_template.pdf'],
    ['label' => 'A0 - 20mm safe area', 'id' => '1:15', 'template_file_name' => 'A0_20mm_print_template.pdf'],
    ['label' => 'A0 - 30mm safe area', 'id' => '1:16', 'template_file_name' => 'A0_30mm_print_template.pdf'],
    ['label' => 'A1 - 0mm safe area', 'id' => '2:17', 'template_file_name' => 'A1_0mm_print_template.pdf'],
    ['label' => 'A1 - 10mm safe area', 'id' => '2:18', 'template_file_name' => 'A1_10mm_print_template.pdf'],
    ['label' => 'A1 - 20mm safe area', 'id' => '2:19', 'template_file_name' => 'A1_20mm_print_template.pdf'],
    ['label' => 'A1 - 30mm safe area', 'id' => '2:20', 'template_file_name' => 'A1_30mm_print_template.pdf'],
    ['label' => 'A2 - 0mm safe area', 'id' => '3:21', 'template_file_name' => 'A2_0mm_print_template.pdf'],
    ['label' => 'A2 - 10mm safe area', 'id' => '3:22', 'template_file_name' => 'A2_10mm_print_template.pdf'],
    ['label' => 'A2 - 20mm safe area', 'id' => '3:23', 'template_file_name' => 'A2_20mm_print_template.pdf'],
    ['label' => 'A2 - 30mm safe area', 'id' => '3:24', 'template_file_name' => 'A2_30mm_print_template.pdf'],
    ['label' => 'A3 - 0mm safe area', 'id' => '4:25', 'template_file_name' => 'A3_0mm_print_template.pdf'],
    ['label' => 'A3 - 10mm safe area', 'id' => '4:26', 'template_file_name' => 'A3_10mm_print_template.pdf'],
    ['label' => 'A3 - 20mm safe area', 'id' => '4:27', 'template_file_name' => 'A3_20mm_print_template.pdf'],
    ['label' => 'A3 - 30mm safe area', 'id' => '4:28', 'template_file_name' => 'A3_30mm_print_template.pdf'],
    ['label' => 'A4 - 0mm safe area', 'id' => '5:29', 'template_file_name' => 'A4_0mm_print_template.pdf'],
    ['label' => 'A4 - 10mm safe area', 'id' => '5:30', 'template_file_name' => 'A4_10mm_print_template.pdf'],
    ['label' => 'A4 - 20mm safe area', 'id' => '5:31', 'template_file_name' => 'A4_20mm_print_template.pdf'],
    ['label' => 'A4 - 30mm safe area', 'id' => '5:32', 'template_file_name' => 'A4_30mm_print_template.pdf'],
    ['label' => 'A5 - 0mm safe area', 'id' => '6:33', 'template_file_name' => 'A5_0mm_print_template.pdf'],
    ['label' => 'A5 - 10mm safe area', 'id' => '6:34', 'template_file_name' => 'A5_10mm_print_template.pdf'],
    ['label' => 'A5 - 20mm safe area', 'id' => '6:35', 'template_file_name' => 'A5_20mm_print_template.pdf'],
    ['label' => 'A5 - 30mm safe area', 'id' => '6:36', 'template_file_name' => 'A5_30mm_print_template.pdf'],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sku_list'])) {
    // Get selected template ID
    $selected_id = $_POST['template_id'];

    // Split the id into print_size_id and template_id
    list($print_size_id, $template_id) = explode(":", $selected_id);

    // Perform the queries
    $query1 = $pdo->prepare("SELECT * FROM print_templates WHERE id = :template_id");
    $query1->execute(['template_id' => $template_id]);
    $template_data = $query1->fetch(PDO::FETCH_ASSOC);

    $query2 = $pdo->prepare("SELECT * FROM print_size_configs WHERE id = :print_size_id");
    $query2->execute(['print_size_id' => $print_size_id]);
    $size_data = $query2->fetch(PDO::FETCH_ASSOC);

    // Get the user inputs for the customizable fields
    $total_prints = isset($_POST['total_prints']) ? $_POST['total_prints'] : 1;
    $background_colour = isset($_POST['background_colour']) ? $_POST['background_colour'] : '';
    $print_background_colour = isset($_POST['print_background_colour']) ? $_POST['print_background_colour'] : 0;
    $forced_orientation = isset($_POST['forced_orientation']) ? $_POST['forced_orientation'] : '';
    $location_preview_image = isset($_POST['location_preview_image']) ? $_POST['location_preview_image'] : '';
    $has_canvas_mask_safe_area = isset($_POST['has_canvas_mask_safe_area']) ? $_POST['has_canvas_mask_safe_area'] : 0;
    $canvas_mask = isset($_POST['canvas_mask']) ? $_POST['canvas_mask'] : NULL;
    $clipping_mask = isset($_POST['clipping_mask']) ? $_POST['clipping_mask'] : NULL;
    $overlay_mask = isset($_POST['overlay_mask']) ? $_POST['overlay_mask'] : NULL;

    // Generate SQL Insert Statements for SKUs
    $skus = explode(",", $_POST['sku_list']); // Split the SKU list by commas
    $sqlStatements = ''; 
    foreach ($skus as $sku) {
        $sku = trim($sku); // Trim any extra spaces

        // Determine the orientation based on width and height
        $orientation = ($size_data['width_mm'] > $size_data['height_mm']) ? 'landscape' : 'portrait';

        // If user specified forced orientation, use it instead of calculated one
        if ($forced_orientation) {
            $orientation = $forced_orientation;
        }

        // Generate the SQL insert statement
        $sql = "INSERT INTO `printable_product_configs` (`id`, `sku`, `print_size_config_id`, `total_prints`, `background_colour`, `print_background_colour`, `forced_orientation`, `location_preview_image`, `has_canvas_mask_safe_area`, `canvas_mask`, `clipping_mask`, `overlay_mask`, `print_template_id`, `created_at`, `updated_at`, `deleted_at`) 
                VALUES (NULL, '$sku', '{$size_data['id']}', '$total_prints', '$background_colour', '$print_background_colour', '$orientation', '$location_preview_image', '$has_canvas_mask_safe_area', '$canvas_mask', '$clipping_mask', '$overlay_mask', '{$template_data['id']}', NULL, NULL, NULL);";

        // Append the SQL statement to the string
        $sqlStatements .= "<p>$sql</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate SQL Insert Statements</title>
    <!-- Add Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light py-5">

<div class="container">
    <h2 class="text-center mb-4">Generate SQL Insert Statements</h2>

    <form method="POST" class="bg-white p-4 border rounded shadow-sm">
        <div class="mb-3">
            <label for="sku_list" class="form-label">Enter SKUs:</label>
            <textarea name="sku_list" id="sku_list" rows="5" class="form-control" placeholder="Enter SKUs here..."></textarea>
        </div>

        <div class="mb-3">
            <label for="template_id" class="form-label">Select Template:</label>
            <select name="template_id" id="template_id" class="form-select">
                <?php foreach ($templates as $template): ?>
                    <option value="<?= $template['id']; ?>"><?= $template['label']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="total_prints" class="form-label">Total Prints:</label>
                <input type="number" name="total_prints" id="total_prints" class="form-control" value="1">
            </div>

            <div class="col-md-4 mb-3">
                <label for="background_colour" class="form-label">Background Colour:</label>
                <input type="text" name="background_colour" id="background_colour" class="form-control" value="">
            </div>

            <div class="col-md-4 mb-3">
                <label for="print_background_colour" class="form-label">Print Background Colour:</label>
                <input type="number" name="print_background_colour" id="print_background_colour" class="form-control" value="0">
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="forced_orientation" class="form-label">Forced Orientation (portrait/landscape):</label>
                <input type="text" name="forced_orientation" id="forced_orientation" class="form-control" value="portrait">
            </div>

            <div class="col-md-4 mb-3">
                <label for="location_preview_image" class="form-label">Location Preview Image:</label>
                <input type="text" name="location_preview_image" id="location_preview_image" class="form-control" value="">
            </div>

            <div class="col-md-4 mb-3">
                <label for="has_canvas_mask_safe_area" class="form-label">Has Canvas Mask Safe Area (1/0):</label>
                <input type="number" name="has_canvas_mask_safe_area" id="has_canvas_mask_safe_area" class="form-control" value="0">
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-3">
                <label for="canvas_mask" class="form-label">Canvas Mask:</label>
                <input type="text" name="canvas_mask" id="canvas_mask" class="form-control" value="">
            </div>

            <div class="col-md-4 mb-3">
                <label for="clipping_mask" class="form-label">Clipping Mask:</label>
                <input type="text" name="clipping_mask" id="clipping_mask" class="form-control" value="">
            </div>

            <div class="col-md-4 mb-3">
                <label for="overlay_mask" class="form-label">Overlay Mask:</label>
                <input type="text" name="overlay_mask" id="overlay_mask" class="form-control" value="">
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100">Generate SQL</button>
    </form>

    <!-- Bootstrap Modal to display SQL -->
    <?php if (isset($sqlStatements) && !empty($sqlStatements)): ?>
        <div class="modal fade" id="sqlModal" tabindex="-1" aria-labelledby="sqlModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="sqlModalLabel">Generated SQL Statements</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?= $sqlStatements; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="copySQL()">Copy to Clipboard</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var sqlModal = new bootstrap.Modal(document.getElementById('sqlModal'));
            sqlModal.show();

            function copySQL() {
                var range = document.createRange();
                range.selectNode(document.querySelector('.modal-body'));
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
                document.execCommand('copy');
            }
        </script>
    <?php endif; ?>
</div>


</body>
</html>