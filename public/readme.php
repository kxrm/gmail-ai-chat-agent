<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use League\CommonMark\GithubFlavoredMarkdownConverter;

// Use GitHub-flavoured Markdown so that tables and task-lists render
$converter = new GithubFlavoredMarkdownConverter();

// Path to your README.md file
$readmeFilePath = dirname(__DIR__) . '/README.md';

// Check if the README.md file exists
if (file_exists($readmeFilePath)) {
    // Read the Markdown content
    $markdownContent = file_get_contents($readmeFilePath);

    // Convert Markdown to HTML (CommonMark v2 returns string, v1 returns object)
    $converted = $converter->convert($markdownContent);
    $htmlContent = is_object($converted) && method_exists($converted, 'getContent')
        ? $converted->getContent()
        : (string) $converted;

    // Ensure UTF-8 output
    header('Content-Type: text/html; charset=utf-8');

    // Output the HTML with basic styling for readability
    echo "<!DOCTYPE html>";
    echo "<html lang=\"en\">";
    echo "<head>";
    echo "    <meta charset=\"UTF-8\">";
    echo "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
    echo "    <title>README - Local AI Chat</title>";
    echo "    <style>";
    echo "        body { font-family: Arial, sans-serif; line-height: 1.6; max-width: 800px; margin: 20px auto; padding: 20px; background-color: #f4f4f4; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }";
    echo "        h1, h2, h3, h4, h5, h6 { color: #333; margin-top: 1.5em; margin-bottom: 0.5em; }";
    echo "        a { color: #007bff; text-decoration: none; }";
    echo "        a:hover { text-decoration: underline; }";
    echo "        pre { background-color: #eee; padding: 10px; border-radius: 5px; overflow-x: auto; }";
    echo "        code { font-family: 'Courier New', monospace; }";
    echo "        blockquote { border-left: 4px solid #ccc; padding-left: 10px; color: #666; }";
    echo "        ul, ol { margin-left: 20px; }";
    echo "        hr { border: 0; height: 1px; background-color: #ddd; margin: 20px 0; }";
    echo "        table { border-collapse: collapse; width: 100%; margin: 1em 0; }";
    echo "        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }";
    echo "        th { background: #f0f0f0; }";
    echo "    </style>";
    echo "</head>";
    echo "<body>";
    echo "    <a href=\"index.php\">&larr; Back to Chat</a>";
    echo "<hr>";
    echo $htmlContent; // Output the converted HTML
    echo "</body>";
    echo "</html>";
} else {
    echo "<h1>Error: README.md not found!</h1>";
    echo "<p>Please ensure the `README.md` file exists in the project root directory.</p>";
}

?> 