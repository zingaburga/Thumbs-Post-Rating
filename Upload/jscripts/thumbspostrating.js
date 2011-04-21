/**
 * Thumbs Post Rating 1.2 by TY Yew
 * thumbspostrating.js
 */

function thumbRate(rating,pid)
{
	new Ajax.Request('xmlhttp.php?action=tpr&rating=' + rating + '&pid=' + pid + "&ajax=1&my_post_key="+my_post_key,{onComplete:thumbResponse});
	// disable rating immediately
	var x = document.getElementById('tpr_stat_' + pid).rows[0].cells;
	var ud = (rating == 1 ? 'u':'d');
	x[1].innerHTML = '<div class="tpr_thumb tu_r'+ud+'"></div>';
	x[2].innerHTML = '<div class="tpr_thumb td_r'+ud+'"></div>';
	
	return false;
}

function thumbResponse(request)
{
	if(error = request.responseText.match(/<error>(.*)<\/error>/))
		alert("An error occurred when rating the post.\n\n" + error[1]);
	else
	{
		response = request.responseText.split('/');
		if(response[0] != 'success')
			alert("An unknown error occurred when rating the post.");
		else
		{
			var x = document.getElementById('tpr_stat_' + parseInt(response[1])).rows[0].cells;
			x[0].innerHTML = parseInt(response[2]); // up
			x[3].innerHTML = parseInt(response[3]); // down
		}
	}
}
