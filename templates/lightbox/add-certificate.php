<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=us-ascii" />

  <title>Add Exemption Certificate</title>

  <!-- Load google fonts -->
  <link href='//fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700' rel='stylesheet' type='text/css' /><!-- Load lightbox CSS -->
  <link rel="stylesheet" href="{PLUGIN_PATH}css/lightbox.css" type="text/css" />
  
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

<body id="add-certificate">
  <h1>Add Exemption Certificate</h1>

  <p><strong>WARNING:</strong> <em>This is a multistate form. Not all states allow all
  exemptions listed on this form.</em> Purchasers are responsible for knowing if they
  qualify to claim exemption from tax in the state that is due tax on this sale. The
  state that is due tax on this sale will be notified that you claimed exemption from
  sales tax. You will be held liable for any tax and interest, as well as civil and
  criminal penalties imposed by the member state, if you are not eligible to claim this
  exemption. Sellers may not accept a certificate of exemption for an entity-based
  exemption on a sale at a location operated by the seller within the designated state if
  the state does not allow such an entity-based exemption.</p>

  <p id="feedback" class="hidden"></p>

  <form action="#" method="post">
    <h2>Certificate Information</h2>

    <div class="form-row">
      <label for="ExemptState">State</label> 
      <select name="ExemptState" class="required">
        <option value="None">
          Select a State
        </option>

        <option value="AL">
          Alabama
        </option>

        <option value="AK">
          Alaska
        </option>

        <option value="AZ">
          Arizona
        </option>

        <option value="AR">
          Arkansas
        </option>

        <option value="CA">
          California
        </option>

        <option value="CO">
          Colorado
        </option>

        <option value="CT">
          Connecticut
        </option>

        <option value="DE">
          Delaware
        </option>

        <option value="FL">
          Florida
        </option>

        <option value="GA">
          Georgia
        </option>

        <option value="HI">
          Hawaii
        </option>

        <option value="ID">
          Idaho
        </option>

        <option value="IL">
          Illinois
        </option>

        <option value="IN">
          Indiana
        </option>

        <option value="IA">
          Iowa
        </option>

        <option value="KS">
          Kansas
        </option>

        <option value="KY">
          Kentucky
        </option>

        <option value="LA">
          Louisiana
        </option>

        <option value="ME">
          Maine
        </option>

        <option value="MD">
          Maryland
        </option>

        <option value="MA">
          Massachusetts
        </option>

        <option value="MI">
          Michigan
        </option>

        <option value="MN">
          Minnesota
        </option>

        <option value="MS">
          Mississippi
        </option>

        <option value="MO">
          Missouri
        </option>

        <option value="MT">
          Montana
        </option>

        <option value="NE">
          Nebraska
        </option>

        <option value="NV">
          Nevada
        </option>

        <option value="NH">
          New Hampshire
        </option>

        <option value="NJ">
          New Jersey
        </option>

        <option value="NM">
          New Mexico
        </option>

        <option value="NY">
          New York
        </option>

        <option value="NC">
          North Carolina
        </option>

        <option value="ND">
          North Dakota
        </option>

        <option value="OH">
          Ohio
        </option>

        <option value="OK">
          Oklahoma
        </option>

        <option value="OR">
          Oregon
        </option>

        <option value="PA">
          Pennsylvania
        </option>

        <option value="RI">
          Rhode Island
        </option>

        <option value="SC">
          South Carolina
        </option>

        <option value="SD">
          South Dakota
        </option>

        <option value="TN">
          Tennessee
        </option>

        <option value="TX">
          Texas
        </option>

        <option value="UT">
          Utah
        </option>

        <option value="VT">
          Vermont
        </option>

        <option value="VA">
          Virginia
        </option>

        <option value="WA">
          Washington
        </option>

        <option value="DC">
          Washington DC
        </option>

        <option value="WV">
          West Virginia
        </option>

        <option value="WI">
          Wisconsin
        </option>

        <option value="WY">
          Wyoming
        </option>
      </select> 
      <span><em>Select the state under whose laws you are claiming exemption.</em></span>
    </div>

    <div class="form-row">
      <label for="SinglePurchase">Certificate Type</label> 
      <select name="SinglePurchase" id="certificateType" class="required">
        <option value="None" data-text="Please select one.">
          Select One
        </option>

        <option value="true" data-text=
        "This certificate will apply to this purchase only.">
          Single Purchase
        </option>

        <option value="false" data-text=
        "This certificate will remain in effect until canceled by the purchaser.">
          Blanket Purchase
        </option>
      </select> 
      <span id="certExpl"><em>Please select one.</em></span>
    </div>

    <h2>Purchaser Identification</h2>

    <div class="form-row">
      <label for="PurchaserTitle">Title</label> 
      <input type="text" name="PurchaserTitle" /> 
      <span><em>Your title. For example, Mr, Mrs, etc.</em></span>
    </div>

    <div class="form-row">
      <label for="PurchaserFirstName">First Name</label> 
      <input type="text" name="PurchaserFirstName" class="required" /> 
      <span><em>Your first name.</em></span>
    </div>

    <div class="form-row">
      <label for="PurchaserLastName">Last Name</label> 
      <input type="text" name="PurchaserLastName" class="required" /> 
      <span><em>Your last name.</em></span>
    </div>

    <div class="form-row">
      <label for="PurchaserAddress1">Business Address</label> 
      <input type="text" name="PurchaserAddress1" class="required" /> 
      <span><em>Your street address.</em></span>
    </div>

    <div class="form-row">
      <label for="PurchaserCity">City</label> 
      <input type="text" name="PurchaserCity" class="required" /> 
      <span><em>The city where your business is located.</em></span>
    </div>

    <div class="form-row">
      <label for="PurchaserState">State</label> 
      <select name="PurchaserState" class="required">
        <option value="None">
          Select One
        </option>

        <option value="AL">
          Alabama
        </option>

        <option value="AK">
          Alaska
        </option>

        <option value="AZ">
          Arizona
        </option>

        <option value="AR">
          Arkansas
        </option>

        <option value="CA">
          California
        </option>

        <option value="CO">
          Colorado
        </option>

        <option value="CT">
          Connecticut
        </option>

        <option value="DE">
          Delaware
        </option>

        <option value="FL">
          Florida
        </option>

        <option value="GA">
          Georgia
        </option>

        <option value="HI">
          Hawaii
        </option>

        <option value="ID">
          Idaho
        </option>

        <option value="IL">
          Illinois
        </option>

        <option value="IN">
          Indiana
        </option>

        <option value="IA">
          Iowa
        </option>

        <option value="KS">
          Kansas
        </option>

        <option value="KY">
          Kentucky
        </option>

        <option value="LA">
          Louisiana
        </option>

        <option value="ME">
          Maine
        </option>

        <option value="MD">
          Maryland
        </option>

        <option value="MA">
          Massachusetts
        </option>

        <option value="MI">
          Michigan
        </option>

        <option value="MN">
          Minnesota
        </option>

        <option value="MS">
          Mississippi
        </option>

        <option value="MO">
          Missouri
        </option>

        <option value="MT">
          Montana
        </option>

        <option value="NE">
          Nebraska
        </option>

        <option value="NV">
          Nevada
        </option>

        <option value="NH">
          New Hampshire
        </option>

        <option value="NJ">
          New Jersey
        </option>

        <option value="NM">
          New Mexico
        </option>

        <option value="NY">
          New York
        </option>

        <option value="NC">
          North Carolina
        </option>

        <option value="ND">
          North Dakota
        </option>

        <option value="OH">
          Ohio
        </option>

        <option value="OK">
          Oklahoma
        </option>

        <option value="OR">
          Oregon
        </option>

        <option value="PA">
          Pennsylvania
        </option>

        <option value="RI">
          Rhode Island
        </option>

        <option value="SC">
          South Carolina
        </option>

        <option value="SD">
          South Dakota
        </option>

        <option value="TN">
          Tennessee
        </option>

        <option value="TX">
          Texas
        </option>

        <option value="UT">
          Utah
        </option>

        <option value="VT">
          Vermont
        </option>

        <option value="VA">
          Virginia
        </option>

        <option value="WA">
          Washington
        </option>

        <option value="DC">
          Washington DC
        </option>

        <option value="WV">
          West Virginia
        </option>

        <option value="WI">
          Wisconsin
        </option>

        <option value="WY">
          Wyoming
        </option>
      </select> 
      <span><em>The state where your business is located.</em></span>
    </div>

    <div class="form-row">
      <label for="PurchaserZip">ZIP Code</label> 
      <input type="text" name="PurchaserZip" class="required" /> 
      <span><em>Your five digit ZIP code.</em></span>
    </div>

    <div class="form-row">
      <label for="TaxType">Exemption ID</label> 
      <select name="TaxType" data-show-class="taxTypeToggle" class="required">
        <option value="None" data-show="">
          Select One
        </option>

        <option value="FEIN" data-show="">
          Federal Employer ID
        </option>

        <option value="StateIssued" data-show="issuing-state">
          State Issued Exemption ID or Drivers License
        </option>

        <option value="ForeignDiplomat" data-show="issuing-country">
          Foreign Diplomat ID
        </option>
      </select> 
      <span><em>What type of exemption ID do you have?</em></span>
    </div>

    <div class="form-row">
      <label for="IDNumber">Number</label> 
      <input type="text" name="IDNumber" class="required" /> 
      <span><em>Your exemption ID number.</em></span>
    </div>

    <div class="form-row taxTypeToggle hidden" id="issuing-state">
      <label for="StateOfIssue">Issued By:</label> 
      <select name="StateOfIssue" class="required">
        <option value="">
          Select One
        </option>

        <option value="AL">
          Alabama
        </option>

        <option value="AK">
          Alaska
        </option>

        <option value="AZ">
          Arizona
        </option>

        <option value="AR">
          Arkansas
        </option>

        <option value="CA">
          California
        </option>

        <option value="CO">
          Colorado
        </option>

        <option value="CT">
          Connecticut
        </option>

        <option value="DE">
          Delaware
        </option>

        <option value="FL">
          Florida
        </option>

        <option value="GA">
          Georgia
        </option>

        <option value="HI">
          Hawaii
        </option>

        <option value="ID">
          Idaho
        </option>

        <option value="IL">
          Illinois
        </option>

        <option value="IN">
          Indiana
        </option>

        <option value="IA">
          Iowa
        </option>

        <option value="KS">
          Kansas
        </option>

        <option value="KY">
          Kentucky
        </option>

        <option value="LA">
          Louisiana
        </option>

        <option value="ME">
          Maine
        </option>

        <option value="MD">
          Maryland
        </option>

        <option value="MA">
          Massachusetts
        </option>

        <option value="MI">
          Michigan
        </option>

        <option value="MN">
          Minnesota
        </option>

        <option value="MS">
          Mississippi
        </option>

        <option value="MO">
          Missouri
        </option>

        <option value="MT">
          Montana
        </option>

        <option value="NE">
          Nebraska
        </option>

        <option value="NV">
          Nevada
        </option>

        <option value="NH">
          New Hampshire
        </option>

        <option value="NJ">
          New Jersey
        </option>

        <option value="NM">
          New Mexico
        </option>

        <option value="NY">
          New York
        </option>

        <option value="NC">
          North Carolina
        </option>

        <option value="ND">
          North Dakota
        </option>

        <option value="OH">
          Ohio
        </option>

        <option value="OK">
          Oklahoma
        </option>

        <option value="OR">
          Oregon
        </option>

        <option value="PA">
          Pennsylvania
        </option>

        <option value="RI">
          Rhode Island
        </option>

        <option value="SC">
          South Carolina
        </option>

        <option value="SD">
          South Dakota
        </option>

        <option value="TN">
          Tennessee
        </option>

        <option value="TX">
          Texas
        </option>

        <option value="UT">
          Utah
        </option>

        <option value="VT">
          Vermont
        </option>

        <option value="VA">
          Virginia
        </option>

        <option value="WA">
          Washington
        </option>

        <option value="DC">
          Washington DC
        </option>

        <option value="WV">
          West Virginia
        </option>

        <option value="WI">
          Wisconsin
        </option>

        <option value="WY">
          Wyoming
        </option>
      </select> 
      <span><em>What state issued your Exemption ID?</em></span>
    </div>

    <div class="form-row taxTypeToggle hidden" id="issuing-country">
      <label for="StateOfIssue">Issued By:</label> 
      <input type="text" name="StateOfIssue" class="required" /> 
      <span><em>Which country issued your Exemption ID?</em></span>
    </div>

    <div class="form-row">
      <label for="PurchaserBusinessType">Business Type</label> 
      <select name="PurchaserBusinessType" data-show-class="businessToggle" class="required">
        <option value="None" data-show="">
          Select One
        </option>

        <option value="AccommodationAndFoodServices" data-show="">
          Accommodation And Food Services
        </option>

        <option value="Agricultural_Forestry_Fishing_Hunting" data-show="">
          Agricultural/Forestry/Fishing/Hunting
        </option>

        <option value="Construction" data-show="">
          Construction
        </option>

        <option value="FinanceAndInsurance" data-show="">
          Finance or Insurance
        </option>

        <option value="Information_PublishingAndCommunications" data-show="">
          Information Publishing and Communications
        </option>

        <option value="Manufacturing" data-show="">
          Manufacturing
        </option>

        <option value="Mining" data-show="">
          Mining
        </option>

        <option value="RealEstate" data-show="">
          Real Estate
        </option>

        <option value="RentalAndLeasing" data-show="">
          Rental and Leasing
        </option>

        <option value="RetailTrade" data-show="">
          Retail Trade
        </option>

        <option value="TransportationAndWarehousing" data-show="">
          Transportation and Warehousing
        </option>

        <option value="Utilities" data-show="">
          Utilities
        </option>

        <option value="WholesaleTrade" data-show="">
          Wholesale Trade
        </option>

        <option value="BusinessServices" data-show="">
          Business Services
        </option>

        <option value="ProfessionalServices" data-show="">
          Professional Services
        </option>

        <option value="EducationAndHealthCareServices" data-show="">
          Education and Health Care Services
        </option>

        <option value="NonprofitOrganization" data-show="">
          Nonprofit Organization
        </option>

        <option value="Government" data-show="">
          Government
        </option>

        <option value="NotABusiness" data-show="">
          Not a Business
        </option>

        <option value="Other" data-show="otherExplanation">
          Other
        </option>
      </select> 
      <span><em>What is the nature of your business?</em></span>
    </div>

    <div class="form-row businessToggle hidden" id="otherExplanation">
      <label for="PurchaserBusinessTypeOtherValue">Please Explain</label> 
      <input type="text" name="PurchaserBusinessTypeOtherValue" class="required" /> 
      <span><em>Please explain the nature of your business.</em></span>
    </div>

    <div class="form-row">
      <label for="PurchaserExemptionReason">Reason?</label> 
      <select name="PurchaserExemptionReason" data-show-class="reasonToggle" class="required">
        <option value="None" data-show="">
          Select One
        </option>

        <option value="FederalGovernmentDepartment" data-show="fedGovernmentDept">
          Federal Government Department
        </option>

        <option value="StateOrLocalGovernmentName" data-show="localGovernment">
          State Or Local Government
        </option>

        <option value="TribalGovernmentName" data-show="tribalGovernment">
          Tribal Government
        </option>

        <option value="ForeignDiplomat" data-show="foreignDiplomat">
          Foreign Diplomat
        </option>

        <option value="CharitableOrganization" data-show="charity">
          Charitable Organization
        </option>

        <option value="ReligiousOrEducationalOrganization" data-show="religiousOrEd">
          Religious or Educational Organization
        </option>

        <option value="Resale" data-show="resale">
          Resale
        </option>

        <option value="AgriculturalProduction" data-show="agriculturalProduction">
          Agricultural Production
        </option>

        <option value="IndustrialProductionOrManufacturing" data-show=
        "industrialProduction">
          Industrial Production or Manufacturing
        </option>

        <option value="DirectPayPermit" data-show="directPay">
          Direct Pay Permit
        </option>

        <option value="DirectMail" data-show="directMail">
          Direct Mail
        </option>

        <option value="Other" data-show="otherReason">
          Other
        </option>
      </select> <span><em>Why are you exempt?</em></span>
    </div>

    <div class="form-row reasonToggle hidden" id="otherReason">
      <span><label for="PurchaserExemptionReasonValue">Please Explain</label>
      <input type="text" name="PurchaserExemptionReasonValue" class="required" />
      <span><em>Please explain why you are tax exempt.</em></span></span>
    </div>

    <div class="form-row reasonToggle hidden" id="fedGovernmentDept">
      <label for="PurchaserExemptionReasonValue">Dept. Name</label> <input type="text"
      name="PurchaserExemptionReasonValue" class="required" />
    </div>

    <div class="form-row reasonToggle hidden" id="localGovernment">
      <label for="PurchaserExemptionReasonValue">Govt. Name</label> <input type="text"
      name="PurchaserExemptionReasonValue" class="required" />
    </div>

    <div class="form-row reasonToggle hidden" id="tribalGovernment">
      <label for="PurchaserExemptionReasonValue">Tribe Name</label> <input type="text"
      name="PurchaserExemptionReasonValue" class="required" />
    </div>

    <div class="form-row reasonToggle hidden" id="foreignDiplomat">
      <label for="PurchaserExemptionReasonValue">Diplomat ID</label> <input type="text"
      name="PurchaserExemptionReasonValue" class="required" />
    </div>

    <div class="form-row reasonToggle hidden" id="charity">
      <label for="PurchaserExemptionReasonValue">Organization ID</label> <input type=
      "text" name="PurchaserExemptionReasonValue" class="required" />
    </div>

    <div class="form-row reasonToggle hidden" id="religiousOrEd">
      <label for="PurchaserExemptionReasonValue">Organization ID</label> <input type=
      "text" name="PurchaserExemptionReasonValue" class="required" />
    </div>

    <div class="form-row reasonToggle hidden" id="resale">
      <label for="PurchaserExemptionReasonValue">Resale ID</label> <input type="text"
      name="PurchaserExemptionReasonValue" class="required" />
    </div>

    <div class="form-row reasonToggle hidden" id="agriculturalProduction">
      <label for="PurchaserExemptionReasonValue">Agricultural Prod. ID</label>
      <input type="text" name="PurchaserExemptionReasonValue" class="required" />
    </div>

    <div class="form-row reasonToggle hidden" id="industrialProduction">
      <label for="PurchaserExemptionReasonValue">Production ID</label> <input type="text"
      name="PurchaserExemptionReasonValue" class="required" />
    </div>

    <div class="form-row reasonToggle hidden" id="directPay">
      <label for="PurchaserExemptionReasonValue">Permit ID</label> <input type="text"
      name="PurchaserExemptionReasonValue" class="required" />
    </div>

    <div class="form-row reasonToggle hidden" id="directMail">
      <label for="PurchaserExemptionReasonValue">Direct Mail ID</label> <input type=
      "text" name="PurchaserExemptionReasonValue" class="required" />
    </div>

    <div class="form-row">
      <input type="hidden" name="act" value="add" /> 
      <input type="hidden" name="action" value="wootax-update-certificate" /> 
      <button type="button" id="manageCertsBtn" class="grey">Manage Certificates</button> 
      <input type="submit" class="submit floatleft" value="Save Certificate" /> 
      <div id="wootax-loader"></div>
    </div>
  </form>
</body>
</html>