<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=us-ascii" />

  <title>Exemption Certificate</title><!-- Load google fonts -->
  <link href='//fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700' rel='stylesheet' type='text/css' /><!-- Load lightbox CSS -->
  <link rel="stylesheet" href="{PLUGIN_PATH}css/lightbox.css" type="text/css" />
  
  <!-- Load jQuery -->
  <script type="text/javascript" src="//code.jquery.com/jquery-1.11.0.min.js"></script>

  <!-- Load lightbox JS -->
  <script type="text/javascript">
  //<![CDATA[
        var pluginPath = '{PLUGIN_PATH}';
        var certInd = '<?php echo $_GET['certIndex']; ?>';
        var merchantName = '{COMPANY_NAME}';
  //]]>
  </script>
  <script type="text/javascript" src="{PLUGIN_PATH}js/lightbox.js">
</script>
</head>

<body id="previewCert">
  <h1>Exemption Certificate</h1>

  <div id="certificatePreview" title="Exemption Certificate Prepared by TaxCloud">
    <span id="PurchaserName"></span> 
    <span id="PurchaserAddress"></span> 
    <span id="PurchaserState"></span> 
    <span id="PurchaserExemptionReason"></span> 
    <span id="OrderID"></span> 
    <span id="Date"></span> 
    <span id="TaxType"></span> 
    <span id="IDNumber"></span> 
    <span id="PurchaserBusinessType"></span> 
    <span id="MerchantName"></span>
  </div>

  <button id="manageCertsBtn" type="button">Manage Certificates</button>
</body>
</html>