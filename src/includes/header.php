<?php
/**
 * Header component with HTML head and CSS styles
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP File Browser</title>
    <style>
        /* -------------- Reset & Layout -------------- */
        body, html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Inter', sans-serif;
            overflow: hidden; /* Prevent body scroll */
            background-color: #f0f2f5;
        }

        .container {
            display: flex;
            height: 100vh;
        }

        /* -------------- Sidebar -------------- */
        .sidebar {
            width: 280px;
            background-color: #ffffff;
            padding: 20px;
            overflow-y: auto;
            border-right: 1px solid #d1d5db;
            box-shadow: 2px 0 8px rgba(0,0,0,0.05);
            flex-shrink: 0;
        }
        .sidebar h2 {
            margin-top: 0;
            font-size: 1.3em;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar li a {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            text-decoration: none;
            color: #374151;
            border-radius: 6px;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: background-color 0.2s ease, color 0.2s ease;
        }
        .sidebar li a:hover {
            background-color: #e5e7eb;
            color: #111827;
        }
        .sidebar li a.active {
            background-color: #3b82f6;
            color: #ffffff;
            font-weight: 500;
        }
        .sidebar li a.active .item-icon {
            filter: brightness(0) invert(1);
        }
        /* Icon */
        .item-icon {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }
        /* Go-Up link */
        .go-up-link a {
            font-weight: 500;
            color: #10b981;
        }
        .go-up-link a:hover {
            color: #059669;
            background-color: #d1fae5;
        }

        /* -------------- Main Content -------------- */
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            background-color: #ffffff;
        }
        /* Folder meta header */
        .folder-meta {
            display: none; /* Shown only when metadata exists */
            padding: 16px 20px;
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        .folder-meta h3 {
            margin: 0;
            font-size: 1.25em;
            color: #111827;
        }
        .folder-meta p {
            margin: 4px 0 0;
            font-size: 0.95em;
            color: #4b5563;
        }
        /* link style */
        .folder-meta a {
            text-decoration: none;
            color: #2563eb;
        }
        .folder-meta a:hover {
            text-decoration: underline;
        }

        /* Iframe */
        .content-frame {
            flex-grow: 1;
            border: none;
        }
    </style>
</head>
<body>