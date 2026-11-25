/**
 * Footage for F5 (document library). Loaded by capture.js.
 */
module.exports = (steps, c) => {
	const { clearStore, rememberEd, SITE, site, P, W, H, file, admin, editor, style, remember, sleep } = c;
	const lib = async (b, page, h = H, wait = 2500) => {
		await clearStore(b);
		await b.viewport(W, h, 2);
		await b.goto(SITE + '/?page_id=' + page, { wait, until: `document.querySelectorAll('.flipset-library-item').length>0` });
		await style(b, '#wpadminbar{display:none!important}html{margin-top:0!important}');
		await b.eval(`(function(){var t=document.querySelector('.flipset-library');if(t){window.scrollTo(0,t.getBoundingClientRect().top+scrollY-110);}})()`);
		await sleep(900);
	};
	const rec = async (b, shot, map) => { for (const k of Object.keys(map)) { await remember(b, shot, k, map[k]); } };
	const B = { lib: '.flipset-library', controls: '.flipset-library-controls', grid: '.flipset-library-grid', card: '.flipset-library-item', sort: '.flipset-library-sort-field', layout: '.flipset-library-layout', search: '.flipset-library-controls input[type=search]', meta: '.flipset-library-meta' };

	steps.f5_front = async (b) => {
		await lib(b, P.lib);
		await b.shot(file('l_grid'));
		await rec(b, 'l_grid', B);
		// the plain shortcode (no controls)
		await lib(b, P.libplain);
		await b.shot(file('l_plain'));
		await rec(b, 'l_plain', B);
	};

	steps.f5_search = async (b) => {
		await lib(b, P.lib);
		await b.eval(`(function(){var i=document.querySelector('.flipset-library-controls input[type=search]');i.focus();i.value='Lighting';i.dispatchEvent(new Event('input',{bubbles:true}));})()`);
		await sleep(500);
		await b.shot(file('l_typed'));
		await rec(b, 'l_typed', B);
		await b.eval(`document.querySelector('.flipset-library-search-submit').click()`);
		await sleep(2500);
		await b.shot(file('l_found'));
		await rec(b, 'l_found', B);
	};

	steps.f5_sort = async (b) => {
		await lib(b, P.lib);
		await b.eval(`(function(){var s=document.querySelector('.flipset-library-sort-select');s.focus();})()`);
		await sleep(300);
		await b.shot(file('l_sort0'));
		await rec(b, 'l_sort0', B);
		await b.eval(`(function(){var s=document.querySelector('.flipset-library-sort-select');s.value='title-asc';s.dispatchEvent(new Event('change',{bubbles:true}));})()`);
		await sleep(2500);
		await b.eval(`(function(){var t=document.querySelector('.flipset-library');if(t){window.scrollTo(0,t.getBoundingClientRect().top+scrollY-110);}})()`);
		await sleep(600);
		await b.shot(file('l_sorted'));
		await rec(b, 'l_sorted', B);
	};

	steps.f5_layout = async (b) => {
		await lib(b, P.lib);
		await b.eval(`document.querySelector('.flipset-library-layout-btn[data-layout="list"]').click()`);
		await sleep(1500);
		await b.shot(file('l_list'));
		await rec(b, 'l_list', B);
		await b.eval(`document.querySelector('.flipset-library-layout-btn[data-layout="grid"]').click()`);
		await sleep(1200);
		await b.shot(file('l_grid2'));
		await rec(b, 'l_grid2', B);
	};

	steps.f5_open = async (b) => {
		await lib(b, P.lib);
		await rec(b, 'l_grid3', B);
		await b.eval(`(function(){var m=document.querySelector('.flipset-library-meta');if(m){m.scrollIntoView({block:'center'});}})()`);
		await sleep(500);
		await b.shot(file('l_meta'));
		await rec(b, 'l_meta', B);
		await lib(b, P.lib);
		await b.eval(`(function(){var a=document.querySelector('.flipset-library-item .flipset-library-card, .flipset-library-item a, .flipset-library-item button');a.click();})()`);
		await sleep(3500);
		await b.shot(file('l_opened'));
		await rec(b, 'l_opened', Object.assign({ viewer: '.flipset-wrap' }, B));
		await b.eval(`document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}));(document.activeElement||document.body).dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}))`);
		await sleep(1500);
		await b.shot(file('l_closed'));
		await rec(b, 'l_closed', B);
	};

	steps.f5_editor = async (b) => {
		await editor(b, 'Downloads', `[ wp.blocks.createBlock('core/shortcode', { text: '[flipset_library columns="4" sort_control="true" layout_toggle="true"]' }) ]`, { select: 0 });
		await b.shot(file('l_sc_editor'));
		await rememberEd(b, 'l_sc_editor', 'block', '.is-root-container > :last-child');
		await editor(b, 'Downloads', `[ wp.blocks.createBlock('flipset/library', { columns: 3 }) ]`, { select: 0 });
		await b.eval(`(function(){try{wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');}catch(e){}})()`);
		await sleep(2500);
		await b.shot(file('l_block'));
		await rememberEd(b, 'l_block', 'block', '.is-root-container > :last-child');
		await remember(b, 'l_block', 'sidebar', '.interface-interface-skeleton__sidebar, .interface-complementary-area, .editor-sidebar');
		await editor(b, 'Downloads', `[ wp.blocks.createBlock('flipset/library', { columns: 4 }) ]`, { select: 0 });
		await b.eval(`(function(){try{wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/block');}catch(e){}})()`);
		await sleep(2500);
		await b.shot(file('l_block4'));
		await rememberEd(b, 'l_block4', 'block', '.is-root-container > :last-child');
	};
};
