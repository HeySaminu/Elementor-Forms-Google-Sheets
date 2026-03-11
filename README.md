# Elementor Forms Google Sheets Integration

Add Google Sheets as an `Action After Submit` in Elementor Pro forms.

## Features

- Adds `Google Sheets` to Elementor Pro form actions
- Sends form submissions to a Google Apps Script webhook
- Supports per-form sheet tab names
- Optional metadata columns for timestamp and form name
- Activity logging in WordPress admin
- Global default webhook setting

## Requirements

- WordPress
- Elementor
- Elementor Pro
- PHP 7.4+

## Installation

### Option 1: Install the release ZIP

Use one of these generated packages:

- `elementor-forms-google-sheets-installable.zip`
- `dist/elementor-forms-google-sheets-v3.1.0.zip`

Upload through `Plugins -> Add New -> Upload Plugin`, then activate it.

### Option 2: Manual install

Copy the plugin into:

```text
wp-content/plugins/elementor-forms-google-sheets
```

Then activate it in WordPress.

## Setup

1. Open `Settings -> Elementor Forms Sheets`
2. Add your Google Apps Script webhook URL
3. Edit an Elementor form
4. Add `Google Sheets` under `Actions After Submit`
5. Set a sheet tab name if needed
6. Save and test the form

## Google Apps Script

Use a deployed Web App URL ending in `/exec`.

Example script:

```javascript
function doPost(e) {
  try {
    var data = JSON.parse(e.postData.contents);
    var ss = SpreadsheetApp.getActiveSpreadsheet();
    var sheetName = data.sheetName || 'Sheet1';
    var sheet = ss.getSheetByName(sheetName);

    if (!sheet) {
      sheet = ss.insertSheet(sheetName);
    }

    var lastRow = sheet.getLastRow();
    if (lastRow === 0 && data.headers) {
      sheet.appendRow(data.headers);
    }

    if (data.values) {
      sheet.appendRow(data.values);
    }

    return ContentService
      .createTextOutput(JSON.stringify({
        status: 'success',
        message: 'Data added successfully',
        row: sheet.getLastRow()
      }))
      .setMimeType(ContentService.MimeType.JSON);
  } catch (error) {
    return ContentService
      .createTextOutput(JSON.stringify({
        status: 'error',
        message: error.toString()
      }))
      .setMimeType(ContentService.MimeType.JSON);
  }
}
```

Deploy it as:

- `Execute as`: Me
- `Who has access`: Anyone with the link

## Development

Build release ZIPs with:

```bash
scripts/build-plugin.sh
```

This generates:

- `elementor-forms-google-sheets-installable.zip`
- `dist/elementor-forms-google-sheets-v3.1.0.zip`

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

GPL v2 or later
