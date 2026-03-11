# QUICK FIX - Installation Instructions

## ⚠️ Your site is broken because the plugin folder structure was wrong

## 🔧 Fix It Now:

### **Step 1: Remove Broken Plugin**

**Via WordPress Admin:**
1. Go to Plugins
2. Find "Elementor Forms Google Sheets Integration"
3. Click **Deactivate** (this will restore your site)
4. Click **Delete**

**OR via FTP/File Manager:**
1. Go to `/public_html/wp-content/plugins/`
2. Delete the folder: `elementor-addon-fixed`

### **Step 2: Install Correctly**

1. Download the ZIP file I'm providing below
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP
4. Click **Install Now**
5. Click **Activate**

### **Step 3: Verify**

1. Your site should be working now
2. Go to **Settings → Elementor Forms Sheets**
3. Check the debug table shows all ✅

---

## 📁 Correct Folder Structure:

The plugin folder should be named exactly:
```
elementor-forms-google-sheets
```

NOT:
- ❌ elementor-addon
- ❌ elementor-addon-fixed  
- ❌ elementor-forms-google-sheets-master

Inside that folder should be:
```
elementor-forms-google-sheets/
├── elementor-forms-google-sheets.php
└── includes/
    └── action-google-sheets.php
```

---

## 🆘 Emergency Site Recovery:

If your site is still broken after deactivating:

**Via FTP/cPanel File Manager:**
```
1. Connect to your site
2. Go to: /public_html/wp-content/plugins/
3. Find folder: elementor-addon-fixed (or similar)
4. Delete it completely
5. Your site will work immediately
```

**Via SSH:**
```bash
cd /path/to/your/site/wp-content/plugins/
rm -rf elementor-addon-fixed
```

---

## ✅ After Site is Fixed:

1. Use the new ZIP file below
2. Upload via WordPress admin (Plugins → Add New → Upload)
3. This version has the correct structure
4. Will work immediately after activation

---

**Your site will be back online as soon as you remove the broken plugin folder!**
