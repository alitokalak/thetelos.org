(function () {
    'use strict';

    if ( ! wp || ! wp.plugins || ! wp.element ) return;

    var el             = wp.element.createElement;
    var useState       = wp.element.useState;
    var useEffect      = wp.element.useEffect;
    var useRef         = wp.element.useRef;
    var registerPlugin = wp.plugins.registerPlugin;
    var useSelect      = wp.data.useSelect;
    var useDispatch    = wp.data.useDispatch;
    var Spinner        = wp.components.Spinner;

    var PluginDocumentSettingPanel =
        ( wp.editor   && wp.editor.PluginDocumentSettingPanel )  ||
        ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

    if ( ! PluginDocumentSettingPanel ) {
        console.warn( '[TCF] PluginDocumentSettingPanel not found.' );
        return;
    }

    function BookCoverPanel() {

        var postTitle = useSelect( function ( sel ) {
            return sel( 'core/editor' ).getEditedPostAttribute( 'title' ) || '';
        } );

        var postId = useSelect( function ( sel ) {
            return sel( 'core/editor' ).getCurrentPostId();
        } );

        var editPost = useDispatch( 'core/editor' ).editPost;

        // İlk aramanın yapılıp yapılmadığını izle
        var initialDone = useRef( false );

        var _s = useState( {
            query:    '',
            results:  [],
            status:   '',
            loading:  false,
            setting:  false,
            selected: -1,
        } );
        var s   = _s[0];
        var set = function ( u ) {
            _s[1]( function ( p ) { return Object.assign( {}, p, u ); } );
        };

        // ── Başlık her değiştiğinde: arama alanını güncelle + ara ──
        useEffect( function () {
            var q = postTitle.trim();
            if ( ! q || q.length < 4 ) return;

            // Arama alanını her zaman başlıkla güncelle
            set( { query: q } );

            if ( ! initialDone.current ) {
                // İlk yükleme: 400ms sonra ara (Gutenberg'in stabil olması için)
                initialDone.current = true;
                var initTimer = setTimeout( function () { doSearch( q ); }, 400 );
                return function () { clearTimeout( initTimer ); };
            } else {
                // Sonraki değişiklikler: 800ms debounce
                var changeTimer = setTimeout( function () { doSearch( q ); }, 800 );
                return function () { clearTimeout( changeTimer ); };
            }
        }, [ postTitle ] );

        // ── Arama fonksiyonu ────────────────────────────────────────
        function doSearch( q ) {
            q = ( q !== undefined ? q : s.query ).trim();
            if ( ! q ) return;

            set( { loading: true, results: [], status: 'Searching…', selected: -1 } );

            var fd = new FormData();
            fd.append( 'action', 'thetelos_fetch_book_covers' );
            fd.append( 'nonce',  tcfData.nonce );
            fd.append( 'q',      q );

            fetch( ajaxurl, { method: 'POST', body: fd } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( res ) {
                    if ( res.success && res.data && res.data.length ) {
                        set( { results: res.data, loading: false,
                               status: res.data.length + ' cover(s) found — click to use' } );
                    } else {
                        set( { results: [], loading: false,
                               status: 'No covers found. Try a different title.' } );
                    }
                } )
                .catch( function () {
                    set( { loading: false, status: 'Network error.' } );
                } );
        }

        // ── Kapak seç → featured image ──────────────────────────────
        function pickCover( cover, idx ) {
            var pid = postId || tcfData.postId;
            if ( ! pid ) { set( { status: 'Please save the post first.' } ); return; }

            set( { setting: true, selected: idx, status: 'Downloading cover…' } );

            var fd = new FormData();
            fd.append( 'action',    'thetelos_set_book_cover' );
            fd.append( 'nonce',     tcfData.nonce );
            fd.append( 'post_id',   pid );
            fd.append( 'cover_url', cover.cover );
            fd.append( 'title',     cover.title );

            fetch( ajaxurl, { method: 'POST', body: fd } )
                .then( function ( r ) { return r.json(); } )
                .then( function ( res ) {
                    if ( res.success ) {
                        editPost( { featured_media: res.data.attachment_id } );
                        set( { setting: false, status: '✓ Featured image set!' } );
                    } else {
                        set( { setting: false,
                               status: 'Error: ' + ( res.data || 'Unknown error' ) } );
                    }
                } )
                .catch( function () {
                    set( { setting: false, status: 'Network error.' } );
                } );
        }

        // ── Render ──────────────────────────────────────────────────
        return el( PluginDocumentSettingPanel, {
            name:        'thetelos-book-cover',
            title:       'Book Cover Fetcher',
            icon:        'book-alt',
            initialOpen: true,
        },

            el( 'div', { style: { display: 'flex', gap: '6px', marginBottom: '6px' } },
                el( 'input', {
                    type:        'text',
                    value:       s.query,
                    placeholder: 'Book title…',
                    onChange:    function ( e ) { set( { query: e.target.value } ); },
                    onKeyDown:   function ( e ) {
                        if ( e.key === 'Enter' ) { e.preventDefault(); doSearch(); }
                    },
                    style: {
                        flex: 1, padding: '5px 8px', fontSize: '12px',
                        border: '1px solid #8c8f94', borderRadius: '3px',
                        boxSizing: 'border-box',
                    }
                } ),
                el( 'button', {
                    type:     'button',
                    onClick:  function () { doSearch(); },
                    disabled: s.loading || s.setting,
                    style: {
                        padding: '5px 10px',
                        background: ( s.loading || s.setting ) ? '#aaa' : '#00ab6b',
                        color: '#fff', border: 'none', borderRadius: '3px',
                        fontSize: '12px',
                        cursor: ( s.loading || s.setting ) ? 'default' : 'pointer',
                        flexShrink: 0,
                    }
                }, s.loading ? '…' : 'Search' )
            ),

            s.status && el( 'div', {
                style: {
                    fontSize: '11px', marginBottom: '6px',
                    color: s.status.startsWith( '✓' )     ? '#00ab6b'
                         : s.status.startsWith( 'Error' ) ? '#cc1818'
                         : '#888',
                }
            }, s.status ),

            s.loading && el( 'div', { style: { textAlign: 'center', padding: '8px 0' } },
                el( Spinner )
            ),

            ! s.loading && s.results.length > 0 && el( 'div', {
                style: { display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: '5px' }
            },
                s.results.map( function ( cover, i ) {
                    var isSel = s.selected === i;
                    var isDim = s.setting && ! isSel;
                    return el( 'div', {
                        key:     i,
                        title:   cover.title + ( cover.author ? ' — ' + cover.author : '' ),
                        onClick: function () { if ( ! s.setting ) pickCover( cover, i ); },
                        style: {
                            cursor:       s.setting ? 'default' : 'pointer',
                            border:       isSel ? '2px solid #00ab6b' : '2px solid transparent',
                            borderRadius: '3px',
                            overflow:     'hidden',
                            opacity:      isDim ? 0.3 : 1,
                            transition:   'all .15s',
                            background:   '#f0f0f0',
                        }
                    },
                        el( 'img', {
                            src:     cover.cover,
                            alt:     cover.title,
                            loading: 'lazy',
                            style:   { width: '100%', height: '72px', objectFit: 'cover', display: 'block' }
                        } ),
                        el( 'div', {
                            style: {
                                fontSize: '9px', color: '#444',
                                padding: '2px 3px 3px', lineHeight: '1.3',
                                overflow: 'hidden', maxHeight: '24px',
                            }
                        }, cover.title )
                    );
                } )
            )
        );
    }

    registerPlugin( 'thetelos-book-cover', { render: BookCoverPanel } );

} )();
