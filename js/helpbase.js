
/**
 * PHP Helpdesk and knowledge base.
 * 
 * @package     HelpBase
 * @subpackage  Javascript 
 * @author      C. Kevin Provance <kevin.provance@gmail.com>
 * @since       1.0.0
 * @copyright   (c) 2013 - 2014, C. Kevin Provance.  All Rights Reserved.
 */


function hb_insertTag(tag) {
    var text_to_insert = '%%'+tag+'%%';
    hb_insertAtCursor(document.form1.msg, text_to_insert);
    document.form1.message.focus();
}

function hb_insertAtCursor(myField, myValue) {
    if (document.selection) {
        myField.focus();
        sel = document.selection.createRange();
        sel.text = myValue;
    } else if (myField.selectionStart || myField.selectionStart == '0') {
        var startPos = myField.selectionStart;
        var endPos = myField.selectionEnd;
        myField.value = myField.value.substring(0, startPos)
            + myValue
            + myField.value.substring(endPos, myField.value.length);
    } else {
        myField.value += myValue;
    }
}

function hb_changeAll(myID) {
    var d = document.form1;
    var setTo = myID.checked ? true : false;

    for (var i = 0; i < d.elements.length; i++) {
        if(d.elements[i].type == 'checkbox' && d.elements[i].name != 'checkall') {
            d.elements[i].checked = setTo;
        }
    }
}

function hb_attach_disable(ids) {
    for($i=0;$i<ids.length;$i++) {
        if (ids[$i]=='c11'||ids[$i]=='c21'||ids[$i]=='c31'||ids[$i]=='c41'||ids[$i]=='c51') {
            document.getElementById(ids[$i]).checked=false;
        }
        document.getElementById(ids[$i]).disabled=true;
    }
}

function hb_attach_enable(ids) {
    for($i=0;$i<ids.length;$i++) {
        document.getElementById(ids[$i]).disabled=false;
    }
}

function hb_attach_toggle(control, ids) {
    if (document.getElementById(control).checked) {
        hb_attach_enable(ids);
    } else {
        hb_attach_disable(ids);
    }
}

function hb_window(PAGE, HGT, WDT) {
    var HbWin = window.open(PAGE, "hb_window", "height=" + HGT + ", width=" + WDT + ", menubar=0, location=0, toolbar=0, status=0, resizable=1, scrollbars=1");
    HbWin.focus();
}

function hb_toggleLayerDisplay(nr) {
    if (document.all) {
        document.all[nr].style.display = (document.all[nr].style.display == 'none') ? 'block' : 'none';
    } else if (document.getElementById) {
        document.getElementById(nr).style.display = (document.getElementById(nr).style.display == 'none') ? 'block' : 'none';
    }
}

function hb_confirmExecute(myText) {
    if (confirm(myText)) {
        return true;
    }
    return false;
}

function hb_deleteIfSelected(myField, myText) {
    if(document.getElementById(myField).checked) {
        return hb_confirmExecute(myText);
    }
}

function hb_rate(url, element_id) {
    if (url.length==0) {
        return false;
    }

    var element = document.getElementById(element_id);

    xmlHttp = GetXmlHttpObject();
    if (xmlHttp == null) {
        alert ("Your browser does not support AJAX!");
        return;
    }

    xmlHttp.open("GET",url,true);

    xmlHttp.onreadystatechange = function() {
        if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
            element.innerHTML = xmlHttp.responseText;
        }
    }

    xmlHttp.send(null);
}

function stateChanged() {
    if (xmlHttp.readyState == 4) {
        document.getElementById("rating").innerHTML=xmlHttp.responseText;
    }
}

function GetXmlHttpObject(){
    var xmlHttp=null;
    try {
        // Firefox, Opera 8.0+, Safari
        xmlHttp=new XMLHttpRequest();
    } catch (e) {
        // Internet Explorer
        try {
            xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
        } catch (e) {
            xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
        }
    }
    return xmlHttp;
}

var hbKBquery = '';
var hbKBfailed = false;

function hb_suggestKB() {
    var d = document.form1;
    var s = d.subject.value;
    var m = d.message.value;
    var element = document.getElementById('kb_suggestions');

    if (s != '' && m != '' && (hbKBquery != s + " " + m || hbKBfailed == true) ) {
        element.style.display = 'block';
        var params = "p=1&" + "q=" + encodeURIComponent( s + " " + m );
        hbKBquery = s + " " + m;

        xmlHttp=GetXmlHttpObject();
        if (xmlHttp==null) {
            return;
        }

        xmlHttp.open('POST','suggest_articles.php',true);
        xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

        xmlHttp.onreadystatechange = function() {
            if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
                element.innerHTML = xmlHttp.responseText;
                hbKBfailed = false;
            } else {
                hbKBfailed = true;
            }
        }

        xmlHttp.send(params);
    }
    setTimeout('hb_suggestKB();', 2000);
}

function hb_suggestKBsearch(isAdmin) {
    var d = document.searchform;
    var s = d.search.value;
    var element = document.getElementById('kb_suggestions');

    if (isAdmin) {
        var path = 'admin_suggest_articles.php';
    } else {
        var path = 'suggest_articles.php';
    }

    if (s != '' && (hbKBquery != s || hbKBfailed == true) ) {
        element.style.display = 'block';
        var params = "q=" + encodeURIComponent( s );
        hbKBquery = s;

        xmlHttp=GetXmlHttpObject();
        if (xmlHttp==null) {
            return;
        }

        xmlHttp.open('POST', path, true);
        xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

        xmlHttp.onreadystatechange = function() {
            if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
                element.innerHTML = unescape(xmlHttp.responseText);
                hbKBfailed = false;
            } else {
                hbKBfailed = true;
            }
        }
        xmlHttp.send(params);
    }
    setTimeout('hb_suggestKBsearch('+isAdmin+');', 2000);
}

function hb_suggestEmail(isAdmin) {
    var email = document.form1.email.value;
    var element = document.getElementById('email_suggestions');

    if (isAdmin) {
        var path = '../suggest_email.php';
    } else {
        var path = 'suggest_email.php';
    }

    if (email != '') {
        var params = "e=" + encodeURIComponent( email );

        xmlHttp=GetXmlHttpObject();
        if (xmlHttp==null) {
            return;
        }

        xmlHttp.open('POST', path, true);
        xmlHttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

        xmlHttp.onreadystatechange = function() {
            if (xmlHttp.readyState == 4 && xmlHttp.status == 200) {
                element.innerHTML = unescape(xmlHttp.responseText);
                element.style.display = 'block';
            }
        }
        xmlHttp.send(params);
    }
}

function hb_btn(Elem, myClass) {
    Elem.className = myClass;
}

function hb_checkPassword(password) {
    var numbers = "0123456789";
    var lowercase = "abcdefghijklmnopqrstuvwxyz";
    var uppercase = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    var punctuation = "!.@$L#*()%~<>{}[]";

    var combinations = 0;

    if (hb_contains(password, numbers) > 0) {
        combinations += 10;
    }

    if (hb_contains(password, lowercase) > 0) {
        combinations += 26;
    }

    if (hb_contains(password, uppercase) > 0) {
        combinations += 26;
    }

    if (hb_contains(password, punctuation) > 0) {
        combinations += punctuation.length;
    }

    var totalCombinations = Math.pow(combinations, password.length);
    var timeInSeconds = (totalCombinations / 200) / 2;
    var timeInDays = timeInSeconds / 86400
    var lifetime = 3650;
    var percentage = timeInDays / lifetime;

    var friendlyPercentage = hb_cap(Math.round(percentage * 100), 98);

    if (friendlyPercentage < (password.length * 5)) {
        friendlyPercentage += password.length * 5;
    }

    var friendlyPercentage = hb_cap(friendlyPercentage, 98);

    var progressBar = document.getElementById("progressBar");
    progressBar.style.width = friendlyPercentage + "%";

    if (percentage > 1) {
        // strong password
        progressBar.style.backgroundColor = "#3bce08";
        return;
    }

    if (percentage > 0.5) {
        // reasonable password
        progressBar.style.backgroundColor = "#ffd801";
        return;
    }

    if (percentage > 0.10) {
        // weak password
        progressBar.style.backgroundColor = "orange";
        return;
    }

    if (percentage <= 0.10) {
        // very weak password
        progressBar.style.backgroundColor = "red";
        return;
    }

}

function hb_cap(number, max) {
    if (number > max) {
        return max;
    } else {
        return number;
    }
}

function hb_contains(password, validChars) {
    count = 0;

    for (i = 0; i < password.length; i++) {
        var char = password.charAt(i);
        if (validChars.indexOf(char) > -1) {
            count++;
        }
    }

    return count;
}