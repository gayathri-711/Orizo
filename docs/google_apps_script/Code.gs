/**
 * Orixo Transactional Email Relay — Google Apps Script Web App
 *
 * Setup:
 * 1. Go to https://script.google.com and create a New Project.
 * 2. Paste this code into Code.gs.
 * 3. In Project Settings -> Script Properties, add:
 *      Key:   ORIXO_MAIL_SECRET
 *      Value: <long random secret matching APPS_SCRIPT_SECRET in Orixo .env>
 * 4. Click Deploy -> New deployment -> Web app:
 *      Execute as: Me
 *      Who has access: Anyone
 * 5. Copy the Web App URL (ending in /exec) into APPS_SCRIPT_WEBHOOK_URL in Orixo .env.
 */

function doPost(e) {
  try {
    if (!e || !e.postData || !e.postData.contents) {
      return jsonOut({ success: false, error: 'Empty request payload.' });
    }

    var data = JSON.parse(e.postData.contents);
    var expectedSecret = PropertiesService.getScriptProperties().getProperty('ORIXO_MAIL_SECRET');

    if (!expectedSecret || !data.secret || data.secret !== expectedSecret) {
      return jsonOut({ success: false, error: 'Unauthorized: invalid Apps Script secret.' });
    }

    if (!data.to || !data.subject || (!data.htmlBody && !data.textBody)) {
      return jsonOut({ success: false, error: 'Missing required fields (to, subject, body).' });
    }

    var options = {
      name: data.fromName || 'Orixo Studio',
      htmlBody: data.htmlBody || undefined
    };

    if (data.replyTo) {
      options.replyTo = data.replyTo;
    }

    MailApp.sendEmail(
      String(data.to).trim(),
      String(data.subject),
      String(data.textBody || ''),
      options
    );

    return jsonOut({ success: true, message: 'Email sent via Google Apps Script.' });
  } catch (err) {
    return jsonOut({ success: false, error: err && err.message ? err.message : String(err) });
  }
}

function jsonOut(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}
