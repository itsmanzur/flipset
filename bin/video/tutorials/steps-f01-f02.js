/**
 * Footage for F1 (install and embed) and F2 (view modes). Loaded by capture.js, which passes its helpers in.
 */
module.exports = (steps, c) => {
	const { rememberEd, SITE, site, P, W, H, file, admin, front, editor, style, remember, sleep } = c;

	steps.f1_install = async (b) => {
		// The real plugin directory card (needs internet): search "flipset" in Plugins > Add New.
		await admin(b, '/wp-admin/plugin-install.php?s=flipset&tab=search&type=term', null, { wait: 6000, until: `document.querySelectorAll('.plugin-card').length>0` });
		await style(b, '.update-plugins,.plugin-count{display:none!important}');
		await sleep(1500);
		await b.shot(file('plugin_search'));
		await remember(b, 'plugin_search', 'card', '.plugin-card-flipset');
		await remember(b, 'plugin_search', 'install', '.plugin-card-flipset .install-now');
		// Installed Plugins with Flipset active.
		await admin(b, '/wp-admin/plugins.php', null, { wait: 2000 });
		await style(b, '.update-plugins,.plugin-count,.plugin-update-tr,.notice{display:none!important}');
		// Only Flipset in the list: the dev site this runs on has other plugins that do not belong in a tutorial.
		await b.eval("(function(){[].slice.call(document.querySelectorAll('#the-list > tr')).forEach(function(r){if((r.getAttribute('data-plugin')||'').indexOf('foliora/')!==0){r.remove();}});[].slice.call(document.querySelectorAll('.subsubsub,.tablenav .displaying-num,.search-box')).forEach(function(e){e.style.display='none';});})()");
		await b.eval(`(function(){var r=document.querySelector('tr[data-slug="foliora"],tr[data-plugin^="foliora/"]');if(r){r.scrollIntoView({block:'center'});}})()`);
		await sleep(500);
		await b.shot(file('plugins_active'));
		await remember(b, 'plugins_active', 'row', 'tr[data-plugin^="foliora/"]');
		await remember(b, 'plugins_active', 'name', 'tr[data-plugin^="foliora/"] .plugin-title strong');
		// Flipset dashboard with its setup checklist.
		await admin(b, '/wp-admin/admin.php?page=flipset-settings', 'dash', { wait: 3000 });
		await style(b, '.update-plugins{display:none!important}');
		await b.shot(file('dash'));
		await remember(b, 'dash', 'hero', '.fa-hero');
	};

	steps.f1_media = async (b) => {
		await admin(b, '/wp-admin/upload.php?mode=list&post_mime_type=application', 'media_list', { wait: 2500 });
		await style(b, '.update-plugins{display:none!important}');
		// Show the PDFs only: the plugin's own generated cover images are hidden for the footage.
		await b.eval("(function(){[].slice.call(document.querySelectorAll('.wp-list-table tbody tr')).forEach(function(r){if(/flipset-thumb/.test(r.textContent)){r.remove();}});var n=document.querySelectorAll('.wp-list-table tbody tr').length;[].slice.call(document.querySelectorAll('.displaying-num')).forEach(function(e){e.textContent=n+' items';});})()");
		await sleep(300);
		await b.shot(file('media_list'));
		await remember(b, 'media_list', 'table', '.wp-list-table');
		// The attachment screen with the File URL field to copy.
		await admin(b, '/wp-admin/post.php?post=' + site.cat + '&action=edit', null, { wait: 2500 });
		await style(b, '.update-plugins{display:none!important}');
		await b.shot(file('media_edit'));
		await remember(b, 'media_edit', 'url', '#attachment_url');
		await remember(b, 'media_edit', 'copy', '.copy-attachment-url');
	};

	steps.f1_editor = async (b) => {
		const url = JSON.stringify(site.catUrl);
		// Shortcode block with the shortcode typed in.
		await editor(b, 'Product catalogue', `[ wp.blocks.createBlock('core/shortcode', { text: '[flipset file="' + ${url} + '"]' }) ]`, { select: 0 });
		await style(b, '.update-plugins{display:none!important}');
		await b.shot(file('sc_editor'));
		await rememberEd(b, 'sc_editor', 'block', '.is-root-container > :last-child');
		// Flipset Viewer block, nothing chosen yet.
		await editor(b, 'Product catalogue', `[ wp.blocks.createBlock('flipset/viewer', {}) ]`, { select: 0 });
		await b.shot(file('block_empty'));
		await rememberEd(b, 'block_empty', 'block', '.is-root-container > :last-child');
		// Flipset Viewer block with the PDF chosen: first-page preview.
		await editor(b, 'Product catalogue', `[ wp.blocks.createBlock('flipset/viewer', { file: ${url}, height: '520px' }) ]`, { select: 0 });
		await b.shot(file('block_picked'));
		await rememberEd(b, 'block_picked', 'block', '.is-root-container > :last-child');
		// A PDF link pasted on its own line.
		await editor(b, 'Product catalogue', `[ wp.blocks.createBlock('core/paragraph', { content: 'Read our latest catalogue below.' }), wp.blocks.createBlock('core/paragraph', { content: ${url} }) ]`, { select: 1 });
		await b.shot(file('paste_editor'));
		await rememberEd(b, 'paste_editor', 'line', '.is-root-container > :last-child');
	};

	steps.f1_front = async (b) => {
		await front(b, P.view, 'view');
		await remember(b, 'view', 'viewer', '.flipset-wrap');
		await remember(b, 'view', 'toolbar', '.flipset-toolbar');
		await front(b, P.pasted, 'pasted', { scrollTo: '.flipset-wrap', top: 120 });
		await remember(b, 'pasted', 'viewer', '.flipset-wrap');
	};

	// F2: the same PDF in four views, the View setting in the block, the More tools menu, a phone.
	steps.f2_views = async (b) => {
		await front(b, P.view, 'v_page');
		await remember(b, 'v_page', 'viewer', '.flipset-wrap');
		await front(b, P.scroll, 'v_scroll');
		await remember(b, 'v_scroll', 'viewer', '.flipset-wrap');
		await front(b, P.spread, 'v_spread');
		await remember(b, 'v_spread', 'viewer', '.flipset-wrap');
		await front(b, P.flip, 'v_flip');
		await remember(b, 'v_flip', 'viewer', '.flipset-wrap');
		// Turn one page so the spread and the book show two pages.
		for (const [pg, name] of [[P.spread, 'v_spread2'], [P.flip, 'v_flip2']]) {
			await front(b, pg, name);
			await b.eval("document.querySelector('.flipset-next').click()");
			await sleep(2200);
			await b.shot(file(name));
			await remember(b, name, 'viewer', '.flipset-wrap');
		}
	};

	steps.f2_block = async (b) => {
		const url = JSON.stringify(site.catUrl);
		await editor(b, 'Product catalogue', `[ wp.blocks.createBlock('flipset/viewer', { file: ${url}, height: '520px', view: 'flip' }) ]`, { select: 0 });
		await b.eval("(function(){try{wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');}catch(e){}})()");
		await sleep(1500);
		await b.shot(file('block_view'));
		await remember(b, 'block_view', 'sidebar', '.interface-interface-skeleton__sidebar, .interface-complementary-area, .editor-sidebar');
		const found = await b.eval(`(function(){var l=[].slice.call(document.querySelectorAll('.interface-complementary-area label, .interface-complementary-area legend')).filter(function(x){return /^\\s*View\\s*$/.test(x.textContent)})[0];if(!l)return null;var r=l.closest('.components-base-control, .components-select-control')||l;var q=r.getBoundingClientRect();return {x:Math.round(q.left),y:Math.round(q.top),w:Math.round(q.width),h:Math.round(q.height)};})()`);
		if (found) { c.rects.block_view = c.rects.block_view || {}; c.rects.block_view.view = found; } else { console.log('  (no View control in the sidebar)'); }
	};

	steps.f2_toolbar = async (b) => {
		await front(b, P.view, 'toolbar_more');
		const clicked = await b.eval(`(function(){var t=[].slice.call(document.querySelectorAll('.flipset-toolbar button')).filter(function(x){return /more/i.test((x.getAttribute('aria-label')||'')+' '+x.title+' '+x.textContent)})[0];if(!t)return false;t.click();return true;})()`);
		console.log('  more tools clicked:', clicked);
		await sleep(900);
		await b.shot(file('toolbar_more'));
		await remember(b, 'toolbar_more', 'toolbar', '.flipset-toolbar');
		await remember(b, 'toolbar_more', 'menu', '.flipset-more-menu');
	};

	steps.f2_mobile = async (b) => {
		await front(b, P.flip, 'mobile_flip', { w: 390, h: 760, top: 40 });
		await remember(b, 'mobile_flip', 'viewer', '.flipset-wrap');
	};
};
