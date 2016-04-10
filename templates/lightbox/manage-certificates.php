<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=us-ascii" />

  <title>Manage Exemption Certificates</title><!-- Load google fonts -->
  <link href='//fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700' rel='stylesheet' type='text/css' /><!-- Load lightbox CSS -->
  <link rel="stylesheet" href="{PLUGIN_PATH}css/lightbox.css?v=1" type="text/css" />
  
  <!-- Load jQuery -->
  <script type="text/javascript" src="//code.jquery.com/jquery-1.11.0.min.js"></script>

  <!-- Load lightbox JS -->
  <script type="text/javascript">
  //<![CDATA[
        var pluginPath = "{PLUGIN_PATH}";
  //]]>
  </script>
  <script type="text/javascript" src="{PLUGIN_PATH}js/lightbox.js">
</script>
</head>

<body id="manage-certificates">
  <h1>Manage Exemption Certificates</h1>

  <div id="wootax-loading" class="hidden">
    <p>Fetching Certificates... <img src="{PLUGIN_PATH}img/ajax-loader.gif" /></p>
  </div>

  <div id="manageCertificates" class="hidden">
    <p>You have <span id="certCount">0</span> certificate(s) on file with TaxCloud.
    Please select an existing certificate below, or add a new one.</p>

    <table id="certs"></table>
  </div>

  <div id="addCertificateSection" class="hidden">
    <p>You do not have any certificates on file with TaxCloud. Please add one by clicking
    the button below.</p>
  </div>

  <button type="button" class="addCert">Add Certificate</button>

  <div id="wootax-loader"></div>
</body>
</html>