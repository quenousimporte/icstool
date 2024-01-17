const https = require('https');
const fs = require('fs');

var settings = null;
var ics = "";
var id = 0;

function ics2json(input)
{
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
	return new Date(
		s.substr(0,4) + "-" + s.substr(4,2) + "-" + s.substr(6,2)
		+ "T"
		+ s.substr(9,2) + ":" + s.substr(11,2) + ":" + s.substr(13,2));
}

function displayevents(events, func, title)
{
	var group = {};
	events
	.filter(func)
	.sort( (a,b) => a.DTSTART - b.DTSTART)
	.forEach(e => {
		var formatteddate = e.DTSTART.toLocaleString('fr-FR', { timeZone: 'Europe/Paris', dateStyle: "full" });
		var splitdate = formatteddate.split(" ");
		var year = splitdate[3];
		var month = splitdate[2];
		group[year] = group[year] || {};
		group[year][month] = group[year][month] || [];
		group[year][month].push(splitdate[0] + " " + splitdate[1] + ": " + e.SUMMARY);
	});
	console.log(title);
	console.log(JSON.stringify(group, null, "    "));;
}
function main()
{
	var o = ics2json(ics);

	displayevents(o.VEVENTS, e => e.DTSTART >= (new Date), "A venir");

	var lastweek = new Date();
	lastweek.setDate(lastweek.getDate() - settings.recentdays);
	displayevents(o.VEVENTS, e => e.DTSTART >= (new Date) && e.DTSTAMP >= lastweek, "Changements récents");
}

fs.readFile('settings.json', 'utf8', (err, data) =>
{
	settings = JSON.parse(data);

	var options =
	{
		host: settings.host,
		port: 443,
		path : settings.path,
		headers: {
			'Authorization': 'Basic ' + Buffer.from(settings.username + ':' + settings.passw).toString('base64')
		}
	};

	request = https.get(options, function(res)
	{
		if (res.statusCode != 200)
		{
			console.log(`statusCode: ${res.statusCode}`)
		}

		res.on('data', chunk => ics += chunk );
		res.on('end', main);
	});

	request.on('error', console.error);
	request.end();
});
