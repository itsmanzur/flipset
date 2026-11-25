# Flipset tutorial videos: Wave 1 scripts (free)

F1, F2, F4 and F5. Same rules as the intro video: one clip per line (the `id`), about half a second of silence before and after each clip, say "WordPress dot org" not "wordpress.org". Voice and language are decided in the tracker.

Progress is tracked in the Flipset video tracker artifact. The Pro scripts are not kept in this repo.

## F1 - Install Flipset and embed your first PDF

Planned length 1:27 (timecodes below follow the speech: 150 words a minute, 0.5 s of silence around each clip, 2 s of screen action per line; re-time after recording). 158 words, about 63 s at 150 words a minute, plus pauses.

**Before recording**

- Clean WordPress site, no other PDF plugin active.
- One sample PDF, 6 to 12 pages, with text and images, already on disk (not yet in Media).
- Browser zoom 125%, window 1920x1080, admin colour scheme default.

| # | id | Time | Voiceover | On screen |
| --- | --- | --- | --- | --- |
| 1 | `hook` | 0:00 | Want a PDF on your WordPress site, without sending visitors off to download it? Here's how, in about ninety seconds. | A page with a blank PDF-link; then the title card "Embed a PDF in WordPress". |
| 2 | `install` | 0:11 | Open Plugins, Add New, and search for Flipset. Install it, activate it, and you land on a short setup checklist. | Plugins > Add New > search Flipset > Install > Activate. Dashboard checklist appears. |
| 3 | `upload` | 0:22 | Upload your PDF in the Media Library. Open it, and copy the file address. | Media > Add New > drop the PDF > open it > copy the file URL. |
| 4 | `shortcode` | 0:31 | Method one: the shortcode. Type flipset, then file equals your address, in square brackets. Preview the page, and the viewer is right there, with zoom, search and fullscreen in the toolbar. | New page, Shortcode block: [flipset file="..."]. Preview: viewer loads; hover the toolbar buttons. |
| 5 | `block` | 0:46 | Method two: the block. Add the Flipset Viewer block, choose your PDF from the Media Library, and the first page shows up as a preview. | Insert block "Flipset Viewer" > choose PDF > first-page preview in the editor. |
| 6 | `paste` | 0:59 | Method three: paste a direct PDF link on its own line. It turns into the viewer by itself. | Paste the .pdf URL on a blank line; the viewer appears. |
| 7 | `same` | 1:09 | Pick the way you like. They all show the same viewer, and it all runs from your own site, with no CDN. | Split screen: the three results. Chips: "No CDN", "No account". |
| 8 | `close` | 1:21 | Next up: view modes, and the 3D flipbook. | End card with the next video title. |

**YouTube**

- Title: How to embed a PDF in WordPress (free plugin, 3 ways)
- Description: Embed a PDF in WordPress with Flipset: a shortcode, the Gutenberg block, or a pasted link. Free, no CDN, no account.
- Chapters: 0:00 hook, 0:11 install, 0:22 upload, 0:31 shortcode, 0:46 block, 0:59 paste, 1:09 same

## F2 - View modes and the 3D flipbook

Planned length 1:08 (timecodes below follow the speech: 150 words a minute, 0.5 s of silence around each clip, 2 s of screen action per line; re-time after recording). 117 words, about 47 s at 150 words a minute, plus pauses.

**Before recording**

- A magazine-style PDF: cover, two-page spreads, 8+ pages.
- Four test pages, one per view, or one page with four embeds.
- Headphones or system-audio capture on, to record the page-turn sound.

| # | id | Time | Voiceover | On screen |
| --- | --- | --- | --- | --- |
| 1 | `hook` | 0:00 | One PDF. Four ways to read it. | The same PDF in four small frames. |
| 2 | `modes` | 0:06 | Flipset has page view, continuous scroll, a two-page spread, and a 3D flipbook. You choose with the view setting. | Cut between page, scroll, spread, flip. Lower-third label for each. |
| 3 | `setting` | 0:16 | In the shortcode, add view equals flip. In the block, open the View setting and choose 3D FlipBook. | Shortcode with view="flip" highlighted; then block sidebar > View > 3D FlipBook. |
| 4 | `curl` | 0:27 | Drag a page corner, and the page curls, with a soft page-turn sound. The sound is made in the browser, so there are no audio files to load. | Flipbook: drag a corner, curl. Show the sound on (speaker icon). |
| 5 | `toolbar` | 0:41 | Readers can change the view themselves, from the More tools menu in the toolbar. | Toolbar > More tools > switch view. |
| 6 | `mobile` | 0:49 | On a narrow screen, the spread and the flipbook switch to a single page. Swipe to turn, pinch to zoom. | Phone-width viewport: single page, swipe gesture overlay. |
| 7 | `close` | 1:00 | Four views, all in the free plugin. Next: the Shortcode Builder. | End card. |

**YouTube**

- Title: WordPress 3D flipbook from a PDF: free plugin tutorial
- Description: Show a PDF as a page, continuous scroll, two-page spread or a 3D flipbook with page curl and page-turn sound. Free plugin, no CDN.
- Chapters: 0:00 hook, 0:06 modes, 0:16 setting, 0:27 curl, 0:41 toolbar, 0:49 mobile

## F4 - Shortcode Builder and saved embeds

Planned length 1:01 (timecodes below follow the speech: 150 words a minute, 0.5 s of silence around each clip, 2 s of screen action per line; re-time after recording). 101 words, about 40 s at 150 words a minute, plus pauses.

**Before recording**

- Two test pages already created, empty.
- At least one PDF in Media.
- Check the exact button labels in Flipset > Shortcode Builder before recording (Save embed, Copy).

| # | id | Time | Voiceover | On screen |
| --- | --- | --- | --- | --- |
| 1 | `hook` | 0:00 | Setting up an embed shouldn't mean memorising options. | A long shortcode with many attributes, then a strike-through. |
| 2 | `open` | 0:06 | Open Flipset, then Shortcode Builder, and pick a PDF. | Flipset > Shortcode Builder. Choose the PDF; the preview loads. |
| 3 | `live` | 0:13 | Change a setting, like the theme, the height, or the download button, and the preview updates straight away. | Toggle theme, drag height, switch download off. Preview reacts each time. |
| 4 | `presets` | 0:23 | Not sure where to start? Use a one-click preset: 3D FlipBook, E-Book Reader, Corporate Report, Night Reader, View Only, or Speed Optimized. | Click each preset chip quickly (1 s each), settle on 3D FlipBook. |
| 5 | `save` | 0:35 | Happy with it? Save the embed. Flipset gives you a short shortcode: flipset, id equals one. | Save embed; the result shows [flipset id="1"]. Copy. |
| 6 | `reuse` | 0:44 | Paste it into any page. And when you edit the saved embed later, every page that uses it updates. | Paste into page A and page B. Edit the embed (theme), reload both: both changed. |
| 7 | `close` | 0:55 | Next: build a searchable library of all your PDFs. | End card. |

**YouTube**

- Title: Flipset Shortcode Builder: live preview and reusable PDF embeds
- Description: Build a PDF embed with a live preview, use a one-click preset, save it once, and reuse it on any page with a short shortcode.
- Chapters: 0:00 hook, 0:06 open, 0:13 live, 0:23 presets, 0:35 save, 0:44 reuse

## F5 - Build a searchable PDF document library

Planned length 1:20 (timecodes below follow the speech: 150 words a minute, 0.5 s of silence around each clip, 2 s of screen action per line; re-time after recording). 140 words, about 56 s at 150 words a minute, plus pauses.

**Before recording**

- 12 to 24 PDFs with different titles in Media.
- Open Flipset > Docs & Features once first, so covers, page counts and the search text exist.
- A page ready for the shortcode; library options sort_control, layout and layout_toggle available (Flipset 1.1.2).

| # | id | Time | Voiceover | On screen |
| --- | --- | --- | --- | --- |
| 1 | `hook` | 0:00 | Got a lot of PDFs? Show them all in one place. | Media Library with many PDFs, zooming out. |
| 2 | `add` | 0:07 | Add the flipset library shortcode to a page, or insert the Flipset Library block. Your Media Library PDFs appear as a grid of covers. | Page: [flipset_library]. Front end: grid of covers. |
| 3 | `search` | 0:20 | Visitors can search, and the grid updates without reloading the page. Flipset searches the text inside your PDFs, too. | Type a word found only inside a PDF; the grid narrows in place. |
| 4 | `sort` | 0:31 | Turn on the sort menu, to order by newest, oldest, or title. | Add sort_control="true". Open the menu; pick Title A to Z; grid reorders. |
| 5 | `layout` | 0:38 | Add the grid and list switch, and visitors can pick their favourite layout. Flipset remembers it in their own browser, nothing is stored on your site. | Add layout_toggle="true". Click List, then Grid. Reload: choice kept. |
| 6 | `open` | 0:52 | Click a cover, and the document opens right there. Press Escape, and you're back where you were. Cards even show the page count. | Open a card; viewer in place; Esc; focus returns. Zoom to "PDF · 12 pages · 198.9 KB". |
| 7 | `block` | 1:04 | In the block, columns, documents per page, order, the sort menu and the layout are all in the sidebar. | Library block sidebar; change columns to 4, preview updates. |
| 8 | `close` | 1:15 | Next: choose your own cover images. | End card. |

**YouTube**

- Title: WordPress PDF document library with search and sort (free)
- Description: Show your Media Library PDFs as a searchable grid or list with a sort menu, page counts and in-place reading. Free Flipset plugin.
- Chapters: 0:00 hook, 0:07 add, 0:20 search, 0:31 sort, 0:38 layout, 0:52 open, 1:04 block

