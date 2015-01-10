/**
 * jQuery TIC select provided courtesy of Fed-Tax (http://taxcloud.net)
 */

var tcJsHost = (("https:" == document.location.protocol) ? "https:" : "http:");
var TaxCloudUrl = tcJsHost + "//taxcloud.net/";
var TaxCloudTicUrl = TaxCloudUrl + "tic/";
var ct = ((typeof currentTic == 'undefined') ? "" : currentTic);
var ddlcss = ((typeof dropdownListCss == 'undefined') ? "font-size:small;background-color:#ECECFF;border:solid 1px #BBBBFF;font-family:'Trebuchet MS', Arial, Helvetica, sans-serif;" : dropdownListCss);
var rescss = ((typeof resultsListCss == 'undefined') ? "font-size:small;color:#666666;text-decoration:none;cursor:default;" : resultsListCss);
var lc = ((typeof linkClass == 'undefined') ? "navlink" : linkClass);
var ub = ((typeof useImageButtons == 'undefined') ? true : useImageButtons);
var so = ((typeof showStartOverLink == 'undefined') ? true : showStartOverLink);
var ws = ((typeof withSubmit == 'undefined') ? false : withSubmit);
var st = ((typeof submitTarget == 'undefined') ? "" : submitTarget);
var sm = ((typeof submitMethod == 'undefined') ? "GET" : submitMethod);
var ticJSON;
var foundTic = false;
var curSelectedTic = "";
var foundTicObj = false;
var prevTIC = "";
var saveTIC = "";
var catListID = "catList";
var ticFinalID = "jqTicFinal";
var ticCompleteID = "ticComplete";

if (jQuery('#' + catListID).length != 0) {
    var catListID = "catList" + jQuery('#' + catListID).length;
    var ticFinalID = "jqTicFinal" + jQuery('#' + ticFinalID).length;
    var ticCompleteID = "ticComplete" + jQuery('#' + ticCompleteID).length
}

function initializeSelect() {
    var reqs = new Array("currentTic", "fieldID", "itemID", "itemIDField");
    if (checkRequirements(reqs)) {
        var jqReplace = "#" + window.fieldID;
        jQuery(jqReplace).replaceWith('<span style="' + rescss + '" id="' + catListID + '"></span><span style="' + rescss + '" id="' + ticFinalID + '">[Loading...]</span><span id="' + ticCompleteID + '"></span>');
        jQuery('#catList').html('');
        jQuery('#jqTicFinal').html('<select style="' + ddlcss + '" id="' + window.fieldID + '" onchange="getTic(this)" name="' + window.fieldID + '"><option>[ Loading... ]</option></select>');
        jQuery('#ticComplete').html('');
        jsonp();
        if (ct.length != 0) {
            saveTIC = ct
        }
    }
}

jQuery(document).ready(function() {
    initializeSelect()
});

function checkRequirements(reqs) {
    var x;
    for (x in reqs) {
        var errorString = "undefined";
        var isOK = false;
        try {
            if (typeof eval(reqs[x]) == 'undefined') {
                isOK = false
            } else if (eval(reqs[x]) == "") {
                errorString = "empty";
                if (reqs[x] == "currentTic") {
                    isOK = true
                }
            } else if (eval(reqs[x]) != "") {
                isOK = true
            }
        } catch (e) {
            isOK = false;
            if ((!ws) && ((reqs[x] == "itemIDField") || (reqs[x] == "itemID"))) {
                isOK = true
            }
            if ((ws) && ((reqs[x] == "itemIDField") || (reqs[x] == "itemID"))) {
                errorString += " but is required when <i>withSubmit=true</i>"
            }
        }
        /*if (!isOK) {
            jQuery("body").append("<div style='margin:0px;padding:10px 0px 10px 0px;text-align:center;font-family:verdana;position:relative;top:0px:left:0px;width:" + jQuery("body").width() + ";z-index:100;color:#000000;background:#FFCC00;border:1px solid #000066;'>TaxCloud JS TIC Selector ERROR: javascript <b style='color:#000000;'>" + reqs[x] + "</b> var is <b style='color:#000000;'>" + errorString + "</b>.</div>")
        }*/
    }
    return isOK
}

function revert() {
    if (saveTIC.length == 5) {
        ct = saveTIC;
        foundTic = false
    }
    jQuery('#ticComplete').html("");
    showTic()
}

function showTic() {
    if (ct.length != 5) {
        if ((ct.length != 0) && (ct != "&nbsp;")) {
            alert("The current TIC specified for this item (TIC:" + ct + ") is invalid.\n\nPlease select an appropriate taxability category from the drop down list.")
        }
        jQuery('#catList').html("");
        jQuery('#jqTicFinal').html('<select style="' + ddlcss + '" id="' + window.fieldID + '" onchange="getTic(this)" name="' + window.fieldID + '"><option>[ Loading... ]</option></select>');
        var ticSelector = jQuery("#" + window.fieldID);
        var ticSelector = jQuery("#" + window.fieldID);
        if (ticJSON.length > 0) {
            jQuery.each(ticJSON, function(i, item) {
                addOption(ticSelector, item.tic)
            })
        }
        ticSelector.children(":first").text("[ - Select - ]").attr("selected", true)
    } else {
        jQuery('#catList').html("");
        jQuery('#jqTicFinal').html("<input type='hidden' id='" + window.fieldID + "' value='" + ct + "'/>");
        jQuery('#ticComplete').html("");
        var selectedObj = find(ct, ticJSON);
        jQuery('#catList').prepend(" (TIC:<span title='" + selectedObj.tic.title + ":[Click to edit]' class='" + lc + "' onclick='showTic()' style='cursor:pointer;'>" + selectedObj.tic.id + "</span>)");
        var myTitleString = selectedObj.tic.title;
        if (myTitleString.length > 40) {
            myTitleString = myTitleString.substring(0, 40) + "[...]"
        }
        jQuery('#catList').prepend(myTitleString);
        ct = ""
    }
}

function jsonp() {
    var url = TaxCloudTicUrl + "?format=jsonp"
    url += "&time=";
    url += new Date().getTime().toString();
    var script = document.createElement("script");
    script.setAttribute("src", url);
    script.setAttribute("type", "text/javascript");
    document.body.appendChild(script)
}

function addOption(ticSelector, tic) {
    var hasChildren = "";
    if (tic.children) {
        hasChildren = "..."
    }
    if (tic.ssuta == 'true') {
        ticSelector.append('<option value="' + tic.id + '" title="' + tic.title + ' (SSUTA)" style="font-weight:bold !important;">' + tic.label + ' ' + hasChildren + '</option>')
    } else {
        ticSelector.append('<option value="' + tic.id + '" title="' + tic.title + '">' + tic.label + hasChildren + '</option>')
    }
}

function taxcloudTics(ptics) {
    var ticListObj = ptics.tic_list;
    ticJSON = ptics.tic_list;
    showTic()
}

function saveTic() {
    var newTic = jQuery('#' + window.fieldID).val();
    if (sm.toUpperCase() == "GET") {
        jQuery.get(st, {
            fieldID: newTic,
            itemIDField: itemID
        }, function() {
            jQuery('#ticComplete').html("<i>Saved</i>");
            ct = newTic;
            showTic();
            callbackFromSaveTic()
        })
    } else {
        jQuery.post(st, {
            fieldID: newTic,
            itemIDField: itemID
        }, function() {
            jQuery('#ticComplete').html("<i>Saved</i>");
            ct = newTic;
            showTic();
            callbackFromSaveTic()
        })
    }
}

function find(selectedTic, ticObj) {
    jQuery.each(ticObj, function(i, item) {
        if (item.tic.id == selectedTic) {
            foundTicObj = item;
            foundTic = true
        }
    });
    if (!foundTic) {
        jQuery.each(ticObj, function(i, item) {
            if (item.tic.children) {
                find(selectedTic, item.tic.children)
            }
        })
    }
    if (foundTic) {
        return foundTicObj
    }
}

function getTic(whichTic) {
    if (whichTic.value == 'reset') {
        startTic();
        if (jQuery('input[name="wootax_tic_desc"]').length != 0) {
            jQuery('input[name="wootax_tic_desc"]').val('')
        }
    } else {
        var ticSelector = jQuery("#" + window.fieldID);
        var selectedValue = whichTic.value;
        curSelectedTic = selectedValue;

        var selectedTitle = whichTic[whichTic.selectedIndex].title;
        var selectedLabel = whichTic[whichTic.selectedIndex].innerHTML;
        var done = false;
        var foundchilren = false;

        ticSelector.children().remove();

        if (jQuery('input[name="wootax_tic_desc"]').length != 0) {
            jQuery('input[name="wootax_tic_desc"]').val(selectedLabel);
        }

        if (selectedTitle.indexOf("(SSUTA)") != -1) {
            done = true;
            if (ws) {
                if (ub) {
                    jQuery('#ticComplete').html("&nbsp;&nbsp;<img style='cursor:pointer;' onclick='saveTic()' src='" + TaxCloudUrl + "imgs/24_go.gif' height='22' width='22' alt='Save' title='Save' border='0' align='absmiddle'/>")
                } else {
                    jQuery('#ticComplete').html("&nbsp;&nbsp;<b class='" + lc + "' style='cursor:pointer;' onclick='saveTic()'>Save</b>&nbsp;&nbsp;")
                }
            }
            if ((!ub) && ((ws) && (so))) {
                jQuery('#ticComplete').html(jQuery('#ticComplete').html() + "|")
            }
            if (so) {
                var startOverText = "Start Over";
                if (saveTIC.length == 5) {
                    startOverText = "Revert"
                }
                if (jQuery('#ticComplete').html().indexOf('revert()') == -1) {
                    if (ub) {
                        jQuery('#ticComplete').html(jQuery('#ticComplete').html() + "&nbsp;&nbsp;<img style='cursor:pointer;' onclick='revert()' src='" + TaxCloudUrl + "imgs/24_cancel.gif' height='22' width='22' title='" + startOverText + "' alt='" + startOverText + "' border='0' align='absmiddle'/>")
                    } else {
                        jQuery('#ticComplete').html(jQuery('#ticComplete').html() + "&nbsp;&nbsp;<span class='" + lc + "' style='cursor:pointer;' onclick='revert()'>" + startOverText + "</span>")
                    }
                }
            }
        }
        foundTic = false;
        var selectedObj = find(selectedValue, ticJSON);
        if (selectedObj.tic.children) {
            foundchilren = true;
            ticSelector.prepend('<option value="reset">-- Start Over --</option>');
            if (done) {
                ticSelector.append('<option selected="true" value="' + curSelectedTic + '">[ OK ]</option>')
            } else {
                ticSelector.append('<option selected="true">[ Select further ]</option>')
            }
            jQuery.each(selectedObj.tic.children, function(i, item) {
                addOption(ticSelector, item.tic)
            });
            if (selectedLabel.indexOf("...") != -1) {
                selectedLabel = selectedLabel.substring(0, selectedLabel.length - 3)
            }
            jQuery('#catList').append(selectedLabel + ":")
        } else {
            jQuery('#catList').append(selectedLabel);
            jQuery('#jqTicFinal').html("<input type='hidden' id='" + window.fieldID + "' name='" + window.fieldID + "' value='" + selectedValue + "'/>")
        }
    }
}