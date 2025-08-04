<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Low Stock Alert - {{ $shopName }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #ff6b6b;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            border: 1px solid #e9ecef;
        }
        .alert-icon {
            font-size: 48px;
            color: #ff6b6b;
            text-align: center;
            margin-bottom: 20px;
        }
        .product-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: white;
            border-radius: 6px;
            overflow: hidden;
        }
        .product-table th,
        .product-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        .product-table th {
            background-color: #ff6b6b;
            color: white;
            font-weight: bold;
        }
        .product-table tr:last-child td {
            border-bottom: none;
        }
        .low-stock {
            color: #ff6b6b;
            font-weight: bold;
        }
        .action-button {
            display: inline-block;
            background-color: #ff6b6b;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            margin: 20px 0;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            color: #6c757d;
            font-size: 14px;
        }
        .summary-box {
            background-color: white;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid #ff6b6b;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>⚠️ Low Stock Alert</h1>
        <p>{{ $shopName }}</p>
    </div>
    
    <div class="content">
        <div class="alert-icon">📦</div>
        
        <h2 style="text-align: center; color: #ff6b6b;">{{ count($products) }} Product{{ count($products) > 1 ? 's' : '' }} Need{{ count($products) == 1 ? 's' : '' }} Attention!</h2>
        
        <div class="summary-box">
            <h3>🚨 Alert Summary</h3>
            <p><strong>Shop:</strong> {{ $shopName }} ({{ $shopDomain }})</p>
            <p><strong>Products Below Threshold:</strong> {{ count($products) }}</p>
            <p><strong>Alert Sent:</strong> {{ $sentAt }}</p>
        </div>
        
        <h3>📋 Products Running Low:</h3>
        
        <table class="product-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Variant</th>
                    <th>Current Stock</th>
                    <th>Threshold</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr>
                    <td><strong>{{ $product['product_title'] ?? 'Unknown Product' }}</strong></td>
                    <td>{{ $product['variant_title'] ?? 'Default' }}</td>
                    <td class="low-stock">{{ $product['current_inventory'] ?? 0 }}</td>
                    <td>{{ $product['threshold_quantity'] ?? 0 }}</td>
                    <td>
                        @if(($product['current_inventory'] ?? 0) == 0)
                            <span style="color: #dc3545;">❌ Out of Stock</span>
                        @else
                            <span style="color: #ff6b6b;">⚠️ Low Stock</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        
        <div style="text-align: center; margin: 30px 0;">
            <h3>🎯 Recommended Actions:</h3>
            <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                <li>✅ Review and reorder low stock products</li>
                <li>📞 Contact suppliers for urgent items</li>
                <li>📊 Update inventory levels in Shopify</li>
                <li>⚙️ Adjust thresholds if needed</li>
            </ul>
        </div>
        
        <div style="text-align: center;">
            <a href="https://{{ $shopDomain }}/admin" class="action-button">
                Go to Shopify Admin
            </a>
        </div>
        
        <p style="margin-top: 30px; font-size: 14px; color: #6c757d;">
            <strong>💡 Tip:</strong> You can adjust alert thresholds and frequency in your Low Stock Pulse app settings to better match your inventory needs.
        </p>
    </div>
    
    <div class="footer">
        <p>This alert was sent by Low Stock Pulse app for {{ $shopName }}</p>
        <p>Sent on {{ $sentAt }} | <a href="https://{{ $shopDomain }}/admin/apps">Manage App Settings</a></p>
    </div>
</body>
</html>
