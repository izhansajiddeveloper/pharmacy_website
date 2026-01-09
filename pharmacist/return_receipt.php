<?php
require_once "../config/db.php";

class ReturnReceipt
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getReturnData($return_id)
    {
        $return_id = mysqli_real_escape_string($this->conn, $return_id);

        $receipt_query = "
            SELECT 
                rt.*,
                m.name AS medicine_name,
                m.generic_name,
                u.username AS returned_by_name,
                s.name AS supplier_name,
                s.phone AS supplier_phone,
                s.email AS supplier_email,
                s.address AS supplier_address,
                sb.expiry_date,
                sb.received_date,
                sb.location,
                (rt.purchase_price * rt.quantity) AS total_value
            FROM returns_to_company rt
            JOIN medicines m ON rt.medicine_id = m.id
            JOIN users u ON rt.returned_by = u.id
            JOIN stock_batches sb ON rt.batch_id = sb.id
            LEFT JOIN suppliers s ON sb.supplier_id = s.id
            WHERE rt.id = '$return_id'
        ";

        $receipt_result = mysqli_query($this->conn, $receipt_query);

        if (!$receipt_result || mysqli_num_rows($receipt_result) === 0) {
            return false;
        }

        return mysqli_fetch_assoc($receipt_result);
    }

    public function generateReceiptHTML($receipt_data)
    {
        ob_start();
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Return Receipt - RTN-<?php echo str_pad($receipt_data['id'], 6, '0', STR_PAD_LEFT); ?></title>
            <?php echo $this->getReceiptStyles(); ?>
        </head>

        <body>
            <div class="receipt-container">
                <?php echo $this->getReceiptHeader($receipt_data); ?>
                <?php echo $this->getReceiptInfoSection($receipt_data); ?>
                <?php echo $this->getItemsTable($receipt_data); ?>
                <?php echo $this->getAdditionalInfo($receipt_data); ?>
                <?php echo $this->getSignatureSection($receipt_data); ?>
                <?php echo $this->getFooter(); ?>

                <div class="action-buttons">
                    <button onclick="window.print()" class="btn btn-print">
                        <i class="print-icon"></i> Print Receipt
                    </button>
                    <button onclick="window.history.back()" class="btn btn-back">
                        <i class="back-icon"></i> Go Back
                    </button>
                    <button onclick="downloadAsPDF()" class="btn btn-pdf">
                        <i class="pdf-icon"></i> Download PDF
                    </button>
                </div>
            </div>

            <script>
                function downloadAsPDF() {
                    // You can use a library like jsPDF or html2pdf.js here
                    alert('PDF download functionality would be implemented here');
                }
            </script>
        </body>

        </html>
<?php
        return ob_get_clean();
    }

    private function getReceiptStyles()
    {
        return '
        <style>
            :root {
                --primary-color: #2c3e50;
                --secondary-color: #3498db;
                --accent-color: #e74c3c;
                --light-gray: #f5f5f5;
                --border-color: #ddd;
                --text-dark: #333;
                --text-light: #666;
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: "Segoe UI", Arial, sans-serif;
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            
            .receipt-container {
                max-width: 900px;
                width: 100%;
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
                padding: 40px;
                position: relative;
                overflow: hidden;
            }
            
            .receipt-container::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            }
            
            /* Header Styles */
            .receipt-header {
                text-align: center;
                padding-bottom: 25px;
                margin-bottom: 30px;
                border-bottom: 2px solid var(--border-color);
                position: relative;
            }
            
            .company-name {
                font-size: 32px;
                font-weight: 700;
                color: var(--primary-color);
                margin-bottom: 5px;
                letter-spacing: 1px;
            }
            
            .receipt-title {
                font-size: 24px;
                color: var(--accent-color);
                font-weight: 600;
                margin-bottom: 10px;
            }
            
            .receipt-meta {
                display: flex;
                justify-content: center;
                gap: 30px;
                margin-top: 15px;
                font-size: 14px;
                color: var(--text-light);
            }
            
            .meta-item {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            /* Info Section */
            .info-section {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .info-card {
                background: var(--light-gray);
                border-radius: 8px;
                padding: 20px;
                border-left: 4px solid var(--secondary-color);
            }
            
            .info-card h3 {
                color: var(--primary-color);
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 1px solid var(--border-color);
                font-size: 18px;
            }
            
            .info-row {
                display: flex;
                justify-content: space-between;
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 1px dashed var(--border-color);
            }
            
            .info-row:last-child {
                border-bottom: none;
                margin-bottom: 0;
                padding-bottom: 0;
            }
            
            .info-label {
                font-weight: 600;
                color: var(--text-dark);
                min-width: 150px;
            }
            
            .info-value {
                color: var(--text-light);
                text-align: right;
                flex: 1;
            }
            
            /* Table Styles */
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin: 30px 0;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            }
            
            .items-table th {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                color: white;
                padding: 15px;
                text-align: left;
                font-weight: 600;
            }
            
            .items-table td {
                padding: 15px;
                border-bottom: 1px solid var(--border-color);
            }
            
            .items-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            
            .items-table tr:hover {
                background-color: #f0f7ff;
            }
            
            .total-row {
                background-color: var(--light-gray) !important;
                font-weight: bold;
                font-size: 16px;
            }
            
            .total-row td {
                padding-top: 20px;
                padding-bottom: 20px;
            }
            
            /* Signature Section */
            .signature-section {
                margin-top: 40px;
                padding-top: 30px;
                border-top: 2px solid var(--border-color);
            }
            
            .signature-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 30px;
                margin-top: 20px;
            }
            
            .signature-box {
                text-align: center;
                padding: 20px;
                border: 1px solid var(--border-color);
                border-radius: 8px;
                background: #fff;
            }
            
            .signature-line {
                height: 1px;
                background: var(--text-dark);
                margin: 40px 0 15px;
            }
            
            .signature-label {
                font-weight: 600;
                color: var(--primary-color);
                margin-bottom: 5px;
            }
            
            .signature-company {
                color: var(--text-light);
                font-size: 14px;
            }
            
            /* Stamp */
            .stamp {
                text-align: center;
                margin: 40px auto;
                padding: 20px;
                max-width: 300px;
                border: 3px solid var(--accent-color);
                border-radius: 50%;
                transform: rotate(-5deg);
                position: relative;
                overflow: hidden;
            }
            
            .stamp::before {
                content: "";
                position: absolute;
                top: -50%;
                left: -50%;
                right: -50%;
                bottom: -50%;
                background: linear-gradient(45deg, transparent 45%, rgba(231, 76, 60, 0.1) 50%, transparent 55%);
                animation: shine 3s infinite linear;
            }
            
            @keyframes shine {
                0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
                100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
            }
            
            .stamp-text {
                color: var(--accent-color);
                font-weight: bold;
                font-size: 18px;
                position: relative;
                z-index: 1;
            }
            
            .stamp-date {
                color: var(--text-light);
                font-size: 14px;
                margin-top: 5px;
            }
            
            /* Footer */
            .receipt-footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid var(--border-color);
                text-align: center;
                color: var(--text-light);
                font-size: 14px;
            }
            
            .footer-links {
                display: flex;
                justify-content: center;
                gap: 20px;
                margin-top: 10px;
            }
            
            /* Action Buttons */
            .action-buttons {
                display: flex;
                justify-content: center;
                gap: 15px;
                margin-top: 40px;
                padding-top: 30px;
                border-top: 1px solid var(--border-color);
            }
            
            .btn {
                padding: 12px 24px;
                border: none;
                border-radius: 6px;
                font-weight: 600;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
                font-size: 14px;
            }
            
            .btn-print {
                background: linear-gradient(135deg, var(--primary-color), #1a252f);
                color: white;
            }
            
            .btn-back {
                background: var(--light-gray);
                color: var(--text-dark);
            }
            
            .btn-pdf {
                background: linear-gradient(135deg, #e74c3c, #c0392b);
                color: white;
            }
            
            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            }
            
            /* Responsive Design */
            @media (max-width: 768px) {
                .receipt-container {
                    padding: 20px;
                }
                
                .company-name {
                    font-size: 24px;
                }
                
                .receipt-title {
                    font-size: 20px;
                }
                
                .info-section {
                    grid-template-columns: 1fr;
                }
                
                .signature-grid {
                    grid-template-columns: 1fr;
                }
                
                .action-buttons {
                    flex-direction: column;
                }
                
                .items-table {
                    display: block;
                    overflow-x: auto;
                }
            }
            
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                
                .receipt-container {
                    box-shadow: none;
                    max-width: 100%;
                }
                
                .action-buttons,
                .btn {
                    display: none;
                }
                
                @page {
                    margin: 0.5cm;
                }
            }
        </style>
        ';
    }

    private function getReceiptHeader($data)
    {
        return '
        <div class="receipt-header">
            <h1 class="company-name">MediCare Pharma</h1>
            <h2 class="receipt-title">Return to Company Receipt</h2>
            <div class="receipt-meta">
                <div class="meta-item">
                    <span>Receipt No:</span>
                    <strong>RTN-' . str_pad($data['id'], 6, '0', STR_PAD_LEFT) . '</strong>
                </div>
                <div class="meta-item">
                    <span>Generated:</span>
                    <strong>' . date('d M Y, h:i A') . '</strong>
                </div>
            </div>
        </div>
        ';
    }

    private function getReceiptInfoSection($data)
    {
        return '
        <div class="info-section">
            <div class="info-card">
                <h3>Return Information</h3>
                <div class="info-row">
                    <span class="info-label">Return ID:</span>
                    <span class="info-value">RTN-' . str_pad($data['id'], 6, '0', STR_PAD_LEFT) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Return Date:</span>
                    <span class="info-value">' . date('d M Y, h:i A', strtotime($data['returned_at'])) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Returned By:</span>
                    <span class="info-value">' . htmlspecialchars($data['returned_by_name']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Location:</span>
                    <span class="info-value">' . htmlspecialchars($data['location']) . '</span>
                </div>
            </div>
            
            <div class="info-card">
                <h3>Product Information</h3>
                <div class="info-row">
                    <span class="info-label">Medicine:</span>
                    <span class="info-value">' . htmlspecialchars($data['medicine_name']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Generic Name:</span>
                    <span class="info-value">' . htmlspecialchars($data['generic_name']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Batch Number:</span>
                    <span class="info-value">' . htmlspecialchars($data['batch_no']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Expiry Date:</span>
                    <span class="info-value">' . date('d M Y', strtotime($data['expiry_date'])) . '</span>
                </div>
            </div>
            
            <div class="info-card">
                <h3>Supplier Information</h3>
                <div class="info-row">
                    <span class="info-label">Supplier:</span>
                    <span class="info-value">' . htmlspecialchars($data['supplier_name']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact:</span>
                    <span class="info-value">' . htmlspecialchars($data['supplier_phone']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">' . htmlspecialchars($data['supplier_email']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address:</span>
                    <span class="info-value">' . htmlspecialchars($data['supplier_address']) . '</span>
                </div>
            </div>
        </div>
        ';
    }

    private function getItemsTable($data)
    {
        return '
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>' . htmlspecialchars($data['medicine_name']) . '</strong><br>
                        <small>Batch: ' . htmlspecialchars($data['batch_no']) . ' | Expiry: ' . date('d M Y', strtotime($data['expiry_date'])) . '</small><br>
                        <small>Reason: ' . htmlspecialchars($data['return_reason']) . '</small>
                    </td>
                    <td>' . $data['quantity'] . ' units</td>
                    <td>Rs ' . number_format($data['purchase_price'], 2) . '</td>
                    <td>Rs ' . number_format($data['total_value'], 2) . '</td>
                </tr>
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;"><strong>Total Return Value:</strong></td>
                    <td><strong>Rs ' . number_format($data['total_value'], 2) . '</strong></td>
                </tr>
            </tbody>
        </table>
        ';
    }

    private function getAdditionalInfo($data)
    {
        return '
        <div class="info-section">
            <div class="info-card">
                <h3>Return Details</h3>
                <div class="info-row">
                    <span class="info-label">Return Reason:</span>
                    <span class="info-value">' . htmlspecialchars($data['return_reason']) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Notes:</span>
                    <span class="info-value">' . nl2br(htmlspecialchars($data['return_notes'] ?? 'No additional notes')) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Batch Received Date:</span>
                    <span class="info-value">' . date('d M Y', strtotime($data['received_date'])) . '</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Original Value:</span>
                    <span class="info-value">Rs ' . number_format($data['total_value'], 2) . '</span>
                </div>
            </div>
        </div>
        ';
    }

    private function getSignatureSection($data)
    {
        return '
        <div class="signature-section">
            <div class="signature-grid">
                <div class="signature-box">
                    <div class="signature-label">Pharmacy Representative</div>
                    <div class="signature-line"></div>
                    <div class="signature-company">MediCare Pharma</div>
                    <div style="margin-top: 10px; font-size: 12px; color: #666;">
                        Signature: _______________________
                    </div>
                </div>
                
                <div class="signature-box">
                    <div class="signature-label">Supplier Representative</div>
                    <div class="signature-line"></div>
                    <div class="signature-company">' . htmlspecialchars($data['supplier_name']) . '</div>
                    <div style="margin-top: 10px; font-size: 12px; color: #666;">
                        Signature: _______________________
                    </div>
                </div>
            </div>
            
            <div class="stamp">
                <div class="stamp-text">RETURNED TO COMPANY</div>
                <div class="stamp-date">' . date('d M Y', strtotime($data['returned_at'])) . '</div>
            </div>
        </div>
        ';
    }

    private function getFooter()
    {
        return '
        <div class="receipt-footer">
            <p>This is an official return receipt from MediCare Pharma</p>
            <div class="footer-links">
                <span>📧 pharmacy@medicare.com</span>
                <span>📞 +91 1234567890</span>
                <span>📍 123 Pharma Street, Medical City</span>
            </div>
            <p style="margin-top: 15px; font-style: italic; color: #999;">
                This document is computer generated and valid without signature
            </p>
        </div>
        ';
    }
}

// This file should contain ONLY the class definition above
// NO execution code should be here
// The file ends here - no extra PHP code