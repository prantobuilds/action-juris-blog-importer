# Action Juris AI Blog Importer WordPress Plugin

## 📋 Overview
A robust WordPress plugin that automatically imports AI-generated blog posts from the Action Juris Laravel API into WordPress with full content preservation, media handling, and intelligent processing.

## 🚀 Features

### **Core Import Features**
- ✅ **Dual Blog Import**: Imports both main blogs (`data[]`) and sub blogs (`sub_data[]`)
- ✅ **API Integration**: Connects to `https://api.actionjuris.ca/api/get-all-blog/wp`
- ✅ **Batch Processing**: Automatic 15-blog batches with 2-second delays for large imports
- ✅ **Duplicate Prevention**: Skips existing posts based on slug matching
- ✅ **Date Preservation**: Maintains original publication dates from API

### **Content Processing**
- ✅ **Smart Content Parsing**: Uses `|*|*|` separator to convert text to H2 headings and paragraphs
- ✅ **Internal Link Conversion**: Transforms `[link text || slug || id]` to WordPress permalinks
- ✅ **Layout Support**: Handles two-column image layouts and mixed content-image blocks
- ✅ **Multi-format Support**: Processes `content`, `images`, `content-image`, `image-content` blocks

### **Media Management**
- ✅ **Featured Images**: Downloads and stores locally via `media_handle_sideload()`
- ✅ **Hotlinked Images**: Other images remain hosted on Action Juris CDN
- ✅ **Image Layouts**: Supports vertical and two-column image arrangements
- ✅ **Original URL Storage**: Saves featured image URLs in post meta for reference

### **Category Management**
- ✅ **Country-Based Categories**: Automatically creates categories based on `country_code` field
- ✅ **Smart Category Mapping**: `gb` and `uk` both map to "United Kingdom" category
- ✅ **Category Creation**: Creates categories if they don't exist, reuses if they do
- ✅ **Post Assignment**: Automatically assigns posts to appropriate country categories

### **Performance & Safety**
- ✅ **Batch Processing**: 15 blogs per batch prevents server timeouts
- ✅ **Memory Management**: Increased memory limits per blog processing
- ✅ **Time Management**: Extended time limits for reliable processing
- ✅ **Progress Display**: Real-time output with batch-by-batch progress
- ✅ **Error Handling**: Comprehensive error reporting and graceful failure

### **User Interface**
- ✅ **Admin Integration**: Located under Tools → "Import Action Juris Blogs"
- ✅ **Progress Tracking**: Visual status indicators for import progress
- ✅ **Summary Reports**: Detailed import statistics upon completion
- ✅ **One-Click Import**: Simple button interface for starting imports

## 📁 File Structure
```
actionjuris-blog-importer/
├── actionjuris-blog-importer.php  # Main plugin file
├── README.md                      # This documentation
└── (Optional future extensions)
```

## 🔧 Installation

1. **Upload Plugin:**
   - Upload `actionjuris-blog-importer.php` to `/wp-content/plugins/`
   - Or upload as ZIP via WordPress admin

2. **Activate Plugin:**
   - Go to WordPress Admin → Plugins
   - Find "Action Juris AI Blog Importer"
   - Click "Activate"

3. **Configure:**
   - No configuration needed - uses default API endpoint

## 🚦 Usage

### **Basic Import:**
1. Navigate to **Tools → Import Action Juris Blogs**
2. Click **"Fetch All Blogs from API (Main + Sub Blogs)"**
3. Watch real-time progress in the admin panel
4. Review summary report when complete

### **Import Process Flow:**
```
1. Fetch blog list from API
2. Collect all slugs for internal linking
3. Process in batches of 15
4. For each blog:
   a. Check if already exists (skip if yes)
   b. Fetch full content from API
   c. Process content blocks
   d. Create WordPress post
   e. Set country category
   f. Download featured image
5. Display summary
```

## 🔌 API Integration

### **Endpoints Used:**
- **Blog List**: `GET https://api.actionjuris.ca/api/get-all-blog/wp`
- **Single Blog**: `GET https://api.actionjuris.ca/api/blog/{slug}`
- **Image Storage**: `https://api.actionjuris.ca/storage/{image_path}`

### **API Response Structure:**
```json
{
  "success": true,
  "data": [/* Main blogs */],
  "sub_data": [/* Sub blogs */],
  "latest_blogs": [/* Recent blogs (ignored) */]
}
```

### **Single Blog Structure:**
```json
{
  "data": {
    "intro": "...",
    "layout": [/* Content blocks */],
    "blog": {
      "country_code": "gb",
      "images": [/* All images */],
      /* ... other fields */
    }
  }
}
```

## ⚙️ Technical Details

### **Content Processing:**
- **Heading Detection**: Content before `|*|*|` becomes H2 tags
- **Paragraph Creation**: Content after `|*|*|` becomes P tags
- **Link Conversion**: `[Text || slug || id]` → WordPress permalinks

### **Image Processing:**
- **Featured Images**: Downloaded locally via WordPress media library
- **Content Images**: Hotlinked from Action Juris CDN
- **Two-Column Layouts**: Uses flexbox CSS for responsive layouts

### **Category System:**
- **Slug Generation**: Uses lowercase country code (e.g., `gb`, `ca`, `bd`)
- **Name Mapping**: Country codes to readable names (e.g., `bd` → "Bangladesh")
- **UK/GB Unification**: Both `gb` and `uk` map to "United Kingdom" category

### **Performance Features:**
- **Batch Size**: 15 blogs per batch
- **Batch Delay**: 2 seconds between batches
- **Time Limits**: 60 seconds per blog, 256MB memory
- **Output Buffering**: Real-time progress display

## 📊 Performance Metrics

### **Time Estimates:**
| Blogs | Batches | Estimated Time |
|-------|---------|----------------|
| 50    | 4       | 2-3 minutes    |
| 100   | 7       | 4-5 minutes    |
| 500   | 34      | 20-25 minutes  |
| 1000  | 67      | 40-45 minutes  |

### **Memory Usage:**
- **Per Blog**: ~2-5MB during processing
- **Batch Processing**: Memory cleared between batches
- **Total Safety**: Up to 500 blogs in single import

## 🛡️ Safety Features

### **Error Handling:**
- API connection failures
- JSON parsing errors
- Image download failures
- Duplicate post detection
- Category creation failures

### **Recovery:**
- Individual blog failures don't stop entire import
- Failed blogs are logged and skipped
- Import continues with next blog in batch

### **Limits:**
- **Maximum Recommended**: 500 blogs per import
- **API Timeout**: 30 seconds for initial fetch
- **Blog Timeout**: 60 seconds per blog processing

## 🔄 Update History

### **Version 1.7** (Current)
- ✅ Added country-based category system
- ✅ Added batch processing for large imports
- ✅ Improved time estimation calculation
- ✅ Enhanced progress reporting

### **Version 1.6**
- ✅ Updated API endpoint structure
- ✅ Fixed slug access patterns
- ✅ Added success flag verification

### **Version 1.5**
- ✅ Initial stable release
- ✅ Full content import
- ✅ Featured image handling
- ✅ Internal link processing

## 📝 Configuration Notes

### **Constants:**
```php
// Automatic configuration
$batch_size = 15;          // Blogs per batch
$batch_delay = 2;          // Seconds between batches
$timeout_per_blog = 60;    // Seconds per blog
$memory_per_blog = '256M'; // Memory per blog
```

### **Customization Points:**
1. **API Endpoint**: Hardcoded to Action Juris API
2. **Batch Size**: Fixed at 15 for safety
3. **Country Mapping**: Editable in `actionjuris_get_country_category()`

## 🚨 Limitations & Known Issues

### **Current Limitations:**
1. **No Pagination**: API doesn't support pagination for >1000 blogs
2. **No Resume**: Import can't resume if interrupted
3. **Single Run**: All blogs processed in one request
4. **No Scheduling**: Manual import only, no auto-scheduling

### **Future Enhancements Planned:**
1. Paginated API support for 10,000+ blogs
2. Import resume functionality
3. Scheduled automatic imports
4. Import progress saving
5. Email notifications on completion

## 🤝 Support & Contribution

### **Reporting Issues:**
1. Check API connectivity
2. Verify WordPress permissions
3. Review PHP error logs
4. Test with small batches first

### **Development:**
- **PHP**: 7.4+ recommended
- **WordPress**: 5.6+ compatible
- **cURL**: Required for API calls
- **GD/ImageMagick**: For image processing

## 📄 License & Credits

**Author**: Samiul H Pranto  
**Author URI**: https://profiles.wordpress.org/samiulhpranto/  
**Version**: 1.7  
**License**: GPL v2 or later  

## ⚠️ Important Notes

1. **Backup Recommended**: Always backup database before large imports
2. **Test First**: Test with small batches before full import
3. **Monitor Resources**: Watch server resources during large imports
4. **API Changes**: Plugin may need updates if API structure changes

---

*Last Updated: February 2024*  
*Tested With: WordPress 6.4+, PHP 7.4-8.2*
