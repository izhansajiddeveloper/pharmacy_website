<?php
// Start output buffering to prevent any accidental output
ob_start();

// Include database configuration if not already included
if (!isset($conn)) {
    require_once '../config/db.php';
}

// Include the ReturnReceipt class
require_once 'return_receipt.php';

// Check if return_id is provided
if (!isset($_GET['return_id']) || empty($_GET['return_id'])) {
    ob_end_clean(); // Clear any output buffer
    echo '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Error - Return Receipt</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f0f0f0;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }
            .error-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
            }
            .error-icon {
                font-size: 48px;
                color: #e74c3c;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
            }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin-top: 20px;
            }
            .btn:hover {
                background: #2980b9;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1>Return ID Required</h1>
            <p>Please provide a return ID to view the receipt.</p>
            <p>Usage: view_receipt.php?return_id=123</p>
            <a href="javascript:history.back()" class="btn">Go Back</a>
        </div>
    </body>
    </html>
    ';
    exit;
}

// Sanitize the return_id
$return_id = filter_var($_GET['return_id'], FILTER_SANITIZE_NUMBER_INT);

// Create receipt object
$receipt = new ReturnReceipt($conn);

// Get receipt data
$receipt_data = $receipt->getReturnData($return_id);

if (!$receipt_data) {
    ob_end_clean(); // Clear any output buffer
    echo '
    <!DOCTYPE html>
    <html>
    <head>
        <title>Error - Return Receipt</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f0f0f0;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }
            .error-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
            }
            .error-icon {
                font-size: 48px;
                color: #e74c3c;
                margin-bottom: 20px;
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
            }
            .btn {
                display: inline-block;
                padding: 10px 20px;
                background: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                margin-top: 20px;
            }
            .btn:hover {
                background: #2980b9;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1>Return Record Not Found</h1>
            <p>The return receipt with ID <strong>' . htmlspecialchars($return_id) . '</strong> was not found in the system.</p>
            <p>Please check the return ID and try again.</p>
            <a href="javascript:history.back()" class="btn">Go Back</a>
        </div>
    </body>
    </html>
    ';
    exit;
}

// Clear output buffer and generate the receipt - ONLY ONCE
ob_end_clean();
echo $receipt->generateReceiptHTML($receipt_data);
