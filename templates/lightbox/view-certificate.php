<?php

/**
 * Template for the "View Certificate" screen. You can override this template by copying it
 * to THEME_PATH/sst/lightbox/view-certificate.php.
 *
 * @package Simple Sales Tax
 * @author Brett Porcelli
 * @since 4.7
 * @version 2.0
 */ 

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
} 

// Prepare values for display
$class = $certificate->is_single() ? 'single' : '';

$values = array(
    "PurchaserName"            => $certificate->PurchaserName,
    "PurchaserAddress"         => $certificate->PurchaserAddress1,
    "PurchaserState"           => $certificate->PurchaserState,
    "PurchaserExemptionReason" => $certificate->PurchaserExemptionReason,
    "OrderID"                  => $certificate->SinglePurchaseOrderNumber,
    "Date"                     => date( 'm/d/Y', strtotime( $certificate->CreatedDate ) ),
    "TaxType"                  => $certificate->PurchaserTaxID->TaxType,
    "IDNumber"                 => $certificate->PurchaserTaxID->IDNumber,
    "PurchaserBusinessType"    => $certificate->PurchaserBusinessType,
    "MerchantName"             => $seller_name
); ?>

<!DOCTYPE html>

<html>
<head>
    <title>Exemption Certificate Prepared by TaxCloud</title>

    <!-- Load google fonts -->
    <link href='//fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700' rel='stylesheet' type='text/css'>

    <!-- Lightbox CSS -->
    <style>
        body {
            font-family: 'Open Sans', sans-serif;
            font-weight: 400;
            color: #666666;
            margin: 0px;
            font-size: 14px;
            line-height: 22px;
        }

        html {
            padding: 15px 20px 5px 20px;
        }

        div {
            background: url( '<?php echo $plugin_url .'/assets/img/exemption_certificate750x600.png'; ?>' ) top left no-repeat;
            clear: both;
            width: 750px;
            height: 600px;
            margin-bottom: 15px;
            margin-left: auto;
            margin-right: auto;
            position: relative;
        }

        div.single {
            background: url( '<?php echo $plugin_url .'/assets/img/sp_exemption_certificate750x600.png'; ?>' ) top left no-repeat;
        }

        div span {
            position: absolute;
            text-align: center;
            height: 20px;
            line-height: 20px;
            font-size: 16px;
            font-weight: 700;
            color: #000;
        }

        #PurchaserName {
            top: 205px;
            left: 405px;
            width: 222px;
        }

        #PurchaserAddress {
            top: 227px;
            left: 190px;
            width: 445px;
        }

        #PurchaserState {
            top: 296px;
            left: 215px;
            width: 245px;
        }

        #PurchaserExemptionReason {
            top: 318px;
            left: 180px;
            width: 480px;
        }

        #Date {
            top: 378px;
            left: 445px;
            width: 235px;
        }

        #TaxType {
            top: 407px;
            left: 445px;
            width: 235px;
        }

        #IDNumber {
            top: 436px;
            left: 445px;
            width: 235px;
        }

        #PurchaserBusinessType {
            top: 465px;
            left: 445px;
            width: 235px;
        }

        #MerchantName {
            top: 495px;
            left: 445px;
            width: 235px;
        }

        #OrderID {
            top: 353px;
            left: 445px;
            width: 235px;
        }
    </style>
</head>

<body>
    <div class="<?php echo $class; ?>">
        <?php
            foreach ( $values as $key => $value ) {
                echo "<span id='$key'>$value</span>";
            }
        ?>
    </div>
</body>
</html>