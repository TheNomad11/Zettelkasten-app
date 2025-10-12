# 🗂️ Zettelkasten PHP-App (vibe coded)

A lightweight, self-hosted personal knowledge management system built with PHP. Create interconnected notes with Markdown support, tags, and bidirectional links - inspired by the Zettelkasten method.

## ✨ Features

- **📝 Markdown Support** - Write notes with full Markdown formatting (headings, lists, blockquotes, code blocks, tables)
- **🔗 Bidirectional Linking** - Connect notes using `[[id]]` syntax and see backlinks automatically
- **🏷️ Smart Tagging** - Organize with comma-separated tags and autocomplete suggestions
- **🔍 Powerful Search** - Full-text search across titles, content, and tags with relevance scoring
- **🔖 Browser Bookmarklet** - Quick-capture web pages directly to your Zettelkasten
- **📊 Related Notes** - Automatically discover similar and related notes based on tags and content
- **📱 Mobile-Friendly** - Responsive design that works on all devices
- **🎨 Clean UI** - Modern, colorful interface with smooth scrolling
- **🔒 Secure** - CSRF protection, XSS prevention, and input validation built-in
- **📄 File-Based** - No database required - all notes stored as JSON files
- **⚡ Fast** - Pagination support for large note collections

## 🚀 Quick Start

### Requirements

- PHP 7.4 or higher
- Web server (Apache, Nginx, or PHP built-in server)
- [Parsedown](https://parsedown.org/) library for Markdown rendering

### Installation

1. **Clone the repository**
   ```
   git clone https://github.com/yourusername/zettelkasten-php.git
   cd zettelkasten-php
   ```

2. **Download Parsedown**
   ```
   wget https://raw.githubusercontent.com/erusev/parsedown/master/Parsedown.php
   ```

3. **Create required directories**
   ```
   mkdir zettels
   chmod 755 zettels
   ```

5. **Open in browser**
   
   ```

That's it! You're ready to start creating notes.

## 📖 Usage

### Creating a Note

1. Fill in the "Create New Zettel" form
2. Write content in Markdown format
3. Add comma-separated tags (with autocomplete)
4. Optionally link to other notes by their IDs
5. Click "Create Zettel"

### Linking Notes

Use double brackets to link to other notes:
```
This note relates to [[67123abc45d]] and [[67123def890]].
```

The IDs will be automatically converted to clickable links showing the note titles.

### Using the Bookmarklet

1. Drag the "➕ Add to Zettelkasten" link from the top section to your browser's bookmarks bar
2. Visit any webpage you want to save
3. Click the bookmarklet
4. A popup opens with the URL and page title pre-filled
5. Add your notes and tags, then save

### Searching

- Use the search bar to find notes by title, content, or tags
- Click any tag to see all notes with that tag
- Search results are ranked by relevance

### Advanced Features

- **View Details** - Click to see backlinks, related notes, and similar notes
- **Edit Mode** - Modify existing notes with autocomplete for tags and links
- **Pagination** - Navigate through large note collections (10 per page)

## 🎨 Customization

### Changing Colors

Edit `styles.css` to customize the color scheme:
- Primary color: `#2c3e50`
- Accent color: `#3498db`
- Success color: `#27ae60`
- Warning color: `#f39c12`

### Adjusting Pagination

In `zettelkasten.php`, change:
```
$perPage = 10; // Change to your preferred number
```

## 📁 File Structure

```
zettelkasten-php/
├── zettelkasten.php    # Main application file
├── styles.css          # Stylesheet
├── Parsedown.php       # Markdown parser (download separately)
├── zettels/            # Directory for note storage (auto-created)
│   ├── 671234abcd.txt
│   └── 671235efgh.txt
└── README.md
```

Each note is stored as a JSON file with the following structure:
```
{
    "id": "671234abcd",
    "title": "Note Title",
    "content": "Note content in Markdown",
    "tags": ["tag1", "tag2"],
    "links": ["other_note_id"],
    "created_at": "2025-10-11 20:00:00",
    "updated_at": "2025-10-11 20:30:00"
}
```

## 🔒 Security Features

- **CSRF Protection** - All forms use tokens to prevent cross-site request forgery
- **XSS Prevention** - All output is properly escaped using `htmlspecialchars()`
- **Input Validation** - Tags and IDs are sanitized with regex patterns
- **File Locking** - Prevents concurrent write conflicts
- **Path Traversal Protection** - ID validation prevents unauthorized directory access



## 🙏 Acknowledgments

- [Parsedown](https://parsedown.org/) - Markdown parser for PHP
- [jQuery UI](https://jqueryui.com/) - Autocomplete functionality
- Inspired by [Niklas Luhmann's Zettelkasten method](https://en.wikipedia.org/wiki/Zettelkasten)


Just copy everything including the quadruple backticks at the beginning and end. When you paste it into your README.md file on GitHub, remove the outer quadruple backticks and keep only the content inside. This is the standard way to escape code blocks within documentation!
