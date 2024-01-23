<?php
	require 'settings.php';

	if (isset($_POST['password']))
	{
		$password = $_POST['password'];

		$curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_USERPWD, $user . ':' . $password);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		$result = curl_exec($curl);

		if (!$result)
		{
			die('error');
		}
		curl_close($curl);
		die($result);
	}
?>

<html>
	<header>
		<title>Evénements à venir</title>
	</header>
	<body style="font-family: helvetica; line-height: 24px; font-size: 16px;">
		<div id="content"></div>
		<script type="text/javascript">
function ics2json(input)
{
	var id = 0;
	var root = {};
	var curr = root;
	input.split("\r\n").forEach(l =>
	{
		var key = l.split(":")[0].split(";")[0];
		var val = l.split(":")[1];
		if (key == "BEGIN")
		{
			if (curr[val])
			{
				val += "_" + (id++);
			}
			curr[val] = {
				parent: curr
			};
			curr = curr[val];
		}
		else if (key == "END")
		{
			var parent = curr.parent;
			delete curr.parent;
			curr = parent;
		}
		else
		{
			curr[key] = key.startsWith("DT") ? dt(val) : val;
		}

	});

	root.VCALENDAR.VEVENTS = [];
	Object.keys(root.VCALENDAR)
	.filter(k => (k == "VEVENT" || k.startsWith("VEVENT_")))
	.forEach(k =>
	{
		root.VCALENDAR.VEVENTS.push(root.VCALENDAR[k]);
		delete root.VCALENDAR[k];
	});

	return root.VCALENDAR;
}

function dt(s)
{
	var formatted = s.substr(0,4) + "-" + s.substr(4,2) + "-" + s.substr(6,2);
	if (s.length > 8)
	{
		formatted += "T" + s.substr(9,2) + ":" + s.substr(11,2) + ":" + s.substr(13,2);
	}
	return new Date(formatted);
}

function formatdate(d)
{
	return d.toLocaleString('fr-FR', { timeZone: 'Europe/Paris', dateStyle: "full", timeStyle: "short" });
}

function showresult()
{
	if (xhr.status == 200)
	{
		var params = new URLSearchParams(window.location.search);
		var recent = parseInt(params.get("recent"));
		if (isNaN(recent))
		{
			recent = 7;
		}

		var html = "<h1>Evénements à venir</h1>";
		html += "<p>En gras: modifié les " + recent + " derniers jours</p>";
		var o = ics2json(xhr.responseText);

		var lastmodified = new Date();
		lastmodified.setDate(lastmodified.getDate() - recent);
		var group = {};

		o.VEVENTS
		.filter(e => e.DTSTART >= (new Date))
		.sort( (a,b) => a.DTSTART - b.DTSTART)
		.forEach(e => {
			var formatteddate = formatdate(e.DTSTART);
			var splitdate = formatteddate.split(" ");
			var year = splitdate[3];
			var month = splitdate[2];

			if (!group[year])
			{
				html += `<h2>${year}</h2>`;
				group[year] = {};
			}

			if (!group[year][month])
			{
				html += `<h3>${month}</h3>`;
				group[year][month] = true;
			}

			var line = `<li title="modifié le ${formatdate(e.DTSTAMP)}"> ${formatteddate}: ${e.SUMMARY}`;
			if (e.DTSTAMP >= lastmodified)
			{
				line = `<b>${line}</b>`;
			}
			html += line;
		});

		content.innerHTML = html;
	}
}

var password = localStorage.getItem("icspassword");
if (!password)
{
	password = prompt("password:");
	localStorage.setItem("icspassword", password);
}

var xhr = new XMLHttpRequest();
xhr.onload = showresult;
xhr.open("POST", "ics.php");
xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
xhr.send("password=" + password);

		</script>
	</body>
</html>
