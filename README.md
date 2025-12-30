# 🗂️ Zettelkasten PHP-App (vibe coded)

A lightweight, self-hosted Zettelkasten (slip-box) system for organizing notes, thoughts, and knowledge with powerful linking and discovery features. Vibe-coded mostly with Claude Sonnet 4.5 and double checked with different other models. 

## ✨ Features

### Core Functionality
- **📝 Markdown Support** - Write notes with full Markdown formatting
- **🔗 Internal Linking** - Connect notes using `[[id]]` syntax
- **🏷️ Tagging System** - Organize with comma-separated tags
- **🔍 Smart Search** - Whole-word search across titles, content, and tags
- **📌 Sticky Notes** - Pin an index/overview note to the top of your main page
- **🎲 Random Note** - Discover notes serendipitously
- **📊 Related Notes** - Automatic discovery of related content by tags and similarity
- **🔙 Backlinks** - See which notes link to the current note

### Export & Backup
- **📄 Single Markdown Export** - All notes in one file with table of contents
- **📦 ZIP Export** - Individual markdown files for each note
- **💾 JSON Backup** - Complete structured data export
- **🔗 Working Internal Links** - Links convert properly in all export formats

### User Interface
- **📱 Fully Responsive** - Optimized for desktop, tablet, and mobile
- **📖 Bookmarklet** - Quick-capture web pages directly to your Zettelkasten
- **⌨️ Autocomplete** - Smart tag and note ID suggestions
- **📄 Pagination** - Configurable notes per page
- **🎨 Clean Design** - Modern, distraction-free interface

### Security
- **🔐 Password Authentication** - Bcrypt password hashing
- **🛡️ CSRF Protection** - Token-based request validation
- **⏱️ Session Management** - Automatic timeout after inactivity
- **🔒 Rate Limiting** - Login attempt throttling
- **📁 File Access Control** - Protected data directories

## 🚀 Installation

### Requirements
- PHP 7.4 or higher
- Web server (Apache with mod_rewrite or Nginx)
- Write permissions for the `zettels/` directory

### Quick Setup

1. **Upload files to your web server:**
   ```bash
   /your-domain.com/zettelkasten/
   ├── index.php
   ├── login.php
   ├── logout.php
   ├── export.php
   ├── config.php
   ├── styles.css
   ├── jquery-ui-autocomplete.css
   ├── Parsedown.php
   ├── .htaccess
   └── zettels/ (create this directory)
   ```

2. **Create the zettels directory:**
   ```bash
   mkdir zettels
   chmod 755 zettels
   ```

3. **Configure your credentials in `config.php`:**
   ```php
   define('USERNAME', 'your_username');
   define('PASSWORD_HASH', '$2y$10$...');  // See below
   ```

4. **Generate a password hash:**
   ```bash
   php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
   ```
   Copy the output and paste it as the `PASSWORD_HASH` value.

5. **Set proper file permissions:**
   ```bash
   chmod 600 config.php  # Protect config file
   chmod 755 zettels     # Allow writing notes
   ```

6. **Access your Zettelkasten:**
   Navigate to `https://your-domain.com/zettelkasten/`

## 📖 Usage Guide

### Creating Notes

1. Click the **"➕ Create New Zettel"** button
2. Enter a title and content (Markdown supported)
3. Add tags (comma-separated): `philosophy, productivity, ideas`
4. Link to other notes using their IDs: `abc123, def456`
5. Use internal linking in content: `[[abc123]]` links to note ID abc123

### Markdown Syntax

```markdown
# Heading 1
## Heading 2

**Bold text** and *italic text*

- Bullet list
- Another item

1. Numbered list
2. Another item

[External link](https://example.com)
[[abc123]] - Internal link to note

> Blockquote

`inline code`

```
Code block
```
```

### Internal Linking

Use `[[note_id]]` in your content to link to other notes:
```markdown
This connects to my note about [[67890abcd]] and also [[12345efgh]].
```

The system will automatically convert these to clickable links with the note title.

### Making a Sticky Note (Index Page)

1. Find the note you want to pin
2. Click **"📌 Make Sticky"**
3. The note will appear at the top of your main page
4. Click **"📍 Unpin"** to remove it

### Searching

- Use the search bar to find notes
- Search matches whole words only (searching "book" won't match "Facebook")
- Results are ranked by relevance:
  - Title matches: 3 points
  - Content matches: 2 points
  - Tag matches: 1 point

### Exporting Notes

1. Click **"📥 Export"** in the header
2. Select notes to export (or keep all selected)
3. Choose format:
   - **Single Markdown**: One file with table of contents
   - **ZIP Archive**: Individual .md files for each note
   - **JSON Backup**: Complete data backup

### Bookmarklet Setup

1. Drag the **"➕ Add to Zettelkasten"** link to your bookmarks bar
2. When browsing, click the bookmarklet
3. A popup opens with the page URL and title pre-filled
4. Add your notes and save

### Random Note Discovery

Click the **"🎲 Random Note"** button to view a random note from your collection. Great for:
- Reviewing old notes
- Finding forgotten connections
- Serendipitous rediscovery

## ⚙️ Configuration

Edit `config.php` to customize:

```php
// Session timeout (2 hours default)
define('SESSION_TIMEOUT', 60 * 60 * 2);

// Notes per page
define('ZETTELS_PER_PAGE', 10);

// Related notes shown
define('RELATED_ZETTELS_LIMIT', 5);

// Login security
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Debug mode (set to false in production)
define('APP_DEBUG', false);
```

## 🔒 Security Features

### Authentication
- Bcrypt password hashing (cost factor 10)
- Session timeout after inactivity
- Login attempt rate limiting
- Session regeneration on login

### CSRF Protection
- Token-based request validation
- Token expiration after 6 hours
- Automatic token regeneration

### File Security (via .htaccess)
- Prevents direct access to `.txt` data files
- Blocks access to `config.php`
- Prevents PHP execution in `zettels/` directory
- Blocks access to hidden files (`.htaccess`, `.git`, etc.)

### Best Practices
1. Use HTTPS (SSL/TLS) for your domain
2. Keep `config.php` outside the web root if possible
3. Regularly backup your `zettels/` directory
4. Use a strong password
5. Keep PHP updated

## 📁 File Structure

```
zettelkasten/
├── index.php              # Main application
├── login.php             # Authentication
├── logout.php            # Session termination
├── export.php            # Export functionality
├── config.php            # Configuration (sensitive!)
├── styles.css            # Main stylesheet
├── jquery-ui-autocomplete.css
├── Parsedown.php         # Markdown parser
├── .htaccess            # Apache security rules
├── manifest.json        # PWA manifest (optional)
├── sw.js               # Service worker (optional)
└── zettels/            # Note storage directory
    ├── .tag_cache.json        # Tag cache (auto-generated)
    ├── .sticky_zettel.txt     # Sticky note ID (auto-generated)
    └── [note_id].txt          # Individual notes (JSON format)
```

## 🛠️ Troubleshooting

### Login Issues
- **Wrong password**: Use the password hash generator command
- **Locked out**: Wait 15 minutes or delete session files
- **Session expires quickly**: Check `SESSION_TIMEOUT` in config

### Notes Not Saving
- Check `zettels/` directory permissions (755)
- Verify web server can write to the directory
- Check PHP error logs

### Internal Links Not Working
- Ensure you're using the correct note ID format: `[[abc123]]`
- IDs are alphanumeric only (no spaces or special characters)
- Check that the target note exists

### Export Issues
- **ZIP not working**: Ensure PHP ZipArchive extension is enabled
- **Large exports timing out**: Increase PHP `max_execution_time`
- **Memory errors**: Increase PHP `memory_limit`

### Mobile Display Issues
- Clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)
- Check that `styles.css` is loading properly
- Verify viewport meta tag is present

## 🎨 Customization

### Changing Colors
Edit `styles.css` to modify the color scheme. Main colors:
- Primary: `#3498db` (blue)
- Success: `#27ae60` (green)
- Warning: `#f39c12` (orange)
- Danger: `#e74c3c` (red)
- Purple: `#9b59b6` (random button)
- Teal: `#16a085` (export button)

### Adding Custom Styles
Add your custom CSS at the bottom of `styles.css`:
```css
/* My custom styles */
.zettel {
    /* Your modifications */
}
```

## 📊 Data Format

Notes are stored as JSON files in the `zettels/` directory:

```json
{
    "id": "67890abcd",
    "title": "My Note Title",
    "content": "Note content with [[12345efgh]] internal links",
    "tags": ["productivity", "ideas"],
    "links": ["12345efgh"],
    "created_at": "2025-01-15 10:30:00",
    "updated_at": "2025-01-15 14:20:00"
}
```

## 🔄 Backup & Migration

### Manual Backup
```bash
# Backup all notes
tar -czf zettelkasten-backup-$(date +%Y%m%d).tar.gz zettels/

# Backup including config
tar -czf zettelkasten-full-backup-$(date +%Y%m%d).tar.gz \
  zettels/ config.php
```

### Migration to Other Systems
1. Use the **ZIP Export** feature
2. Each note becomes a `.md` file
3. Internal links use standard markdown: `[Note Title](note_id.md)`
4. Compatible with Obsidian, Logseq, and other markdown tools

## 📚 Zettelkasten Method

This system implements the Zettelkasten (slip-box) method:

1. **Atomic Notes**: Each note contains one idea
2. **Linking**: Connect related ideas with internal links
3. **Tags**: Categorize for easy retrieval
4. **Emergence**: Let structure emerge organically through connections
5. **Progressive Summarization**: Create index notes (sticky notes) for overviews

### Tips for Effective Use
- Write in your own words
- Link liberally - connections spark insights
- Review random notes regularly
- Create structure notes (sticky) as topics develop
- Tag consistently but don't over-categorize
- Focus on ideas, not just collecting information

## 🆘 Support & Contributing

### Getting Help
- Check this README first
- Review your PHP error logs
- Check file permissions
- Verify configuration settings

### Feature Requests
This is a personal/self-hosted project. Feel free to modify the code to add features you need!

## 📜 License

This project is provided as-is for personal use. Modify and customize as needed for your requirements.

## 🙏 Credits

- **Markdown Parser**: [Parsedown](https://parsedown.org/)
- **Autocomplete**: jQuery UI
- **Method**: Based on Niklas Luhmann's Zettelkasten system
- **Design**: Custom responsive design

## 📝 Version History

### v1.0 (Current)
- ✅ Sticky/Index note functionality
- ✅ Random note discovery
- ✅ Export in multiple formats (MD, ZIP, JSON)
- ✅ Improved whole-word search
- ✅ Fully responsive mobile design
- ✅ Enhanced security features
- ✅ Bookmarklet for quick capture

### v0.1 (Initial)
- Basic note creation and editing
- Markdown support
- Internal linking
- Tag system
- Search functionality
- Authentication

e base, one atomic note at a time.
