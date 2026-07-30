<?xml version="1.0" encoding="{$Charset}"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="zh-Hant" xml:lang="zh-Hant">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset={$Charset}" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<style type="text/css">

			body {
			    font: 13px Helvetica, "Lucida Grande", Verdana, Arial, sans-serif;
			    background-color: #fefefe;
			    color: #333333;
			    height: 100%;
			}

			div.resource-image img
			{
			    padding-bottom:25px;
			    max-height:200px;
			    max-width:200px;
			}

			/* Scoped to the reservation-notification card (tpl/Email/reservation-notification-body.tpl)
			   only, so this can't affect any other email (password reset, account creation, ...)
			   that also happens to share this header/footer. Clients that strip <style> from <head>
			   (notably Gmail's Android/iOS apps) just fall back to the inlined widths below, which
			   are already single-column and reasonably narrow — this is a progressive enhancement,
			   not something the layout depends on. */
			@media only screen and (max-width: 620px) {
				.coasia-email-card { width: 100% !important; }
				.coasia-email-stack-cell { display: block !important; width: 100% !important; box-sizing: border-box; }
				.coasia-email-pad { padding-left: 18px !important; padding-right: 18px !important; }
			}

		</style>
	</head>
	<body>
