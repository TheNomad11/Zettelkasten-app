* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f9; color: #333; line-height: 1.6; padding: 20px; }
.container { max-width: 1200px; margin: 0 auto; }
h1 { color: #2c3e50; margin-bottom: 20px; }
h2 { color: #34495e; margin-top: 30px; margin-bottom: 15px; }
h3 { color: #7f8c8d; margin-top: 20px; margin-bottom: 10px; font-size: 1.1em; }

/* Back to all link for single view */
.back-link { display: inline-block; padding: 10px 20px; background: #95a5a6; color: white; text-decoration: none; border-radius: 4px; margin-bottom: 20px; }
.back-link:hover { background: #7f8c8d; }

/* Bookmarklet section */
.bookmarklet-section { background: #e8f5e9; border: 2px solid #4caf50; border-radius: 8px; padding: 20px; margin-bottom: 30px; }
.bookmarklet-section h3 { color: #2e7d32; margin-top: 0; }
.bookmarklet-link { display: inline-block; background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 10px 0; }
.bookmarklet-link:hover { background: #45a049; }
.bookmarklet-instructions { margin-top: 10px; font-size: 0.95em; }

/* Bookmarklet popup mode */
.popup-mode { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.popup-mode h2 { margin-top: 0; color: #4caf50; }

.search-form { display: flex; gap: 10px; margin-bottom: 20px; }
.search-form input[type="text"] { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1em; }
.search-form button { padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; }
.search-form button:hover { background: #2980b9; }

.tag-cloud { background: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
.tag-cloud a { display: inline-block; margin: 5px; padding: 5px 12px; background: #ecf0f1; color: #2c3e50; text-decoration: none; border-radius: 3px; font-size: 0.9em; }
.tag-cloud a:hover { background: #3498db; color: white; }

.create-form { background: white; padding: 20px; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
.create-form label { display: block; margin-top: 15px; font-weight: 600; color: #2c3e50; }
.create-form input[type="text"], .create-form textarea { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; font-size: 1em; font-family: inherit; }
.create-form textarea { min-height: 150px; resize: vertical; }
.create-form button { margin-top: 15px; padding: 12px 24px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; font-weight: 600; }
.create-form button:hover { background: #229954; }

.zettel { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
.zettel-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px; }
.zettel-title { font-size: 1.5em; font-weight: 600; color: #2c3e50; margin: 0; }
.zettel-meta { font-size: 0.85em; color: #7f8c8d; margin-top: 5px; }
.zettel-content { margin: 15px 0; color: #34495e; }

/* Markdown content styling */
.zettel-content h1 { font-size: 1.8em; margin: 20px 0 10px; color: #2c3e50; }
.zettel-content h2 { font-size: 1.5em; margin: 18px 0 10px; color: #34495e; }
.zettel-content h3 { font-size: 1.3em; margin: 16px 0 8px; color: #7f8c8d; }
.zettel-content h4 { font-size: 1.1em; margin: 14px 0 8px; color: #95a5a6; }
.zettel-content h5 { font-size: 1em; margin: 12px 0 6px; font-weight: 600; }
.zettel-content h6 { font-size: 0.9em; margin: 10px 0 6px; font-weight: 600; }

.zettel-content p { margin: 10px 0; line-height: 1.7; }

.zettel-content blockquote { 
    margin: 15px 0; 
    padding: 10px 20px; 
    border-left: 4px solid #3498db; 
    background: #f8f9fa; 
    color: #555;
    font-style: italic;
}
.zettel-content blockquote p { margin: 8px 0; }

.zettel-content code { 
    background: #f4f4f4; 
    padding: 2px 6px; 
    border-radius: 3px; 
    font-family: 'Courier New', monospace; 
    font-size: 0.9em;
    color: #e74c3c;
}

.zettel-content pre { 
    background: #2c3e50; 
    color: #ecf0f1; 
    padding: 15px; 
    border-radius: 5px; 
    overflow-x: auto; 
    margin: 15px 0;
}
.zettel-content pre code { 
    background: none; 
    color: inherit; 
    padding: 0;
}

.zettel-content ul, .zettel-content ol { 
    margin: 10px 0 10px 25px; 
    line-height: 1.8;
}
.zettel-content li { margin: 5px 0; }

.zettel-content hr { 
    border: none; 
    border-top: 2px solid #ecf0f1; 
    margin: 20px 0; 
}

.zettel-content table { 
    border-collapse: collapse; 
    width: 100%; 
    margin: 15px 0; 
}
.zettel-content table th, .zettel-content table td { 
    border: 1px solid #ddd; 
    padding: 10px; 
    text-align: left; 
}
.zettel-content table th { 
    background: #34495e; 
    color: white; 
    font-weight: 600; 
}
.zettel-content table tr:nth-child(even) { background: #f8f9fa; }

.zettel-content a { color: #3498db; text-decoration: none; }
.zettel-content a:hover { text-decoration: underline; }

.zettel-content strong { font-weight: 600; color: #2c3e50; }
.zettel-content em { font-style: italic; }

.zettel-content img { max-width: 100%; height: auto; border-radius: 5px; margin: 10px 0; }

.zettel-tags { margin: 15px 0; }
.zettel-tags span { display: inline-block; margin-right: 8px; padding: 4px 10px; background: #ecf0f1; color: #2c3e50; border-radius: 3px; font-size: 0.85em; }
.zettel-links { margin: 15px 0; }
.zettel-links a { color: #3498db; text-decoration: none; margin-right: 10px; }
.zettel-links a:hover { text-decoration: underline; }
.zettel-actions { display: flex; gap: 10px; margin-top: 15px; }
.zettel-actions button, .zettel-actions a { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 0.9em; display: inline-block; }
.btn-edit { background: #f39c12; color: white; }
.btn-edit:hover { background: #e67e22; }
.btn-delete { background: #e74c3c; color: white; }
.btn-delete:hover { background: #c0392b; }
.btn-view { background: #3498db; color: white; }
.btn-view:hover { background: #2980b9; }

.edit-form { background: #fff9e6; padding: 20px; border-radius: 8px; margin-top: 15px; border: 2px solid #f39c12; }
.edit-form input[type="text"], .edit-form textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; }
.edit-form textarea { min-height: 150px; }
.edit-form button { padding: 10px 20px; margin-right: 10px; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; }
.edit-form .btn-save { background: #27ae60; color: white; }
.edit-form .btn-cancel { background: #95a5a6; color: white; }

.pagination { display: flex; justify-content: center; gap: 10px; margin: 30px 0; }
.pagination a, .pagination span { padding: 8px 12px; background: white; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #2c3e50; }
.pagination a:hover { background: #3498db; color: white; border-color: #3498db; }
.pagination .current { background: #3498db; color: white; border-color: #3498db; font-weight: 600; }

.internal-link { color: #9b59b6; text-decoration: none; font-weight: 500; }
.internal-link:hover { text-decoration: underline; }
.broken-link { color: #e74c3c; font-style: italic; }

.related-section { background: #f8f9fa; padding: 15px; border-radius: 6px; margin-top: 20px; }
.related-section h4 { color: #34495e; margin-bottom: 10px; font-size: 1em; }
.related-section ul { list-style: none; padding: 0; }
.related-section li { padding: 5px 0; }
.related-section a { color: #3498db; text-decoration: none; }
.related-section a:hover { text-decoration: underline; }

.no-results { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 15px; border-radius: 6px; margin: 20px 0; }

@media (max-width: 768px) {
    body { padding: 10px; }
    .search-form { flex-direction: column; }
    .zettel-header { flex-direction: column; }
    .zettel-actions { flex-direction: column; }
}

