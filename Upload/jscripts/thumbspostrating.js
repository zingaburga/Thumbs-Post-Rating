/**
 * Thumbs Post Rating 1.2 by TY Yew
 * thumbspostrating.js
 */

function thumbRate(tu,td,pid)
{
    new Ajax.Request('xmlhttp.php?action=tpr&tu=' + tu + '&td=' + td + '&pid=' + pid,{onComplete:thumbResponse(tu,td,pid)});
}

function thumbResponse(tu,td,pid)
{
    if( tu == 1 )
    {
        var oldresult = document.getElementById('tu_stat_' + pid).innerHTML;
        var newresult = parseInt(oldresult) + 1;

        var x = document.getElementById('tpr_stat_' + pid).rows[0].cells;
        x[0].innerHTML = newresult;
        x[1].innerHTML = '<div class="tpr_thumb tu_ru"></div>';
        x[2].innerHTML = '<div class="tpr_thumb td_ru"></div>';

    }
    else if( td == 1 )
    {
        var oldresult = document.getElementById('td_stat_' + pid).innerHTML;
        var newresult = parseInt(oldresult) + 1;

        var x=document.getElementById('tpr_stat_' + pid).rows[0].cells;
        x[1].innerHTML = '<div class="tpr_thumb tu_rd"></div>';
        x[2].innerHTML = '<div class="tpr_thumb td_rd"></div>';
        x[3].innerHTML = newresult;
    }
    else
    {
        alert('Error!')
    }
}