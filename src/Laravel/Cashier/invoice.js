// PhantomJS Screenshot Capture...
var fs = require('fs'),
    args = require('system').args,
    page = require('webpage').create();

// Read the HTML view into the page...
page.content = fs.read(args[1]);
page.viewportSize = {width: 600, height: 600};
page.paperSize = {format: 'A4', orientation: 'portrait', margin: '1cm'};

// Give the page 250ms to render any images...
window.setTimeout(function() {
	page.render(args[1]);
	phantom.exit();
}, 150);
