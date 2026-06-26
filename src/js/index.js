/**
 * Mega Menu Block Editor Script
 *
 * @package MegaMenuBlock
 */

import { registerBlockType, createBlock, serialize } from '@wordpress/blocks';
import {
	useBlockProps,
	InnerBlocks,
	BlockControls,
	InspectorControls,
	BlockEditorProvider,
	BlockList,
	BlockTools,
	WritingFlow,
	ObserveTyping,
	// In WP 7.0 this is still experimental — exported as __experimentalListView.
	__experimentalListView as ListView,
	BlockInspector,
	// Inline inserter library panel — avoids Popover z-index escaping the modal.
	__experimentalLibrary as InserterLibrary,
	BlockBreadcrumb,
	// WP's own mechanism for injecting settings.styles into the editor canvas.
	// Runs transformStyles (handles baseURL URL rebasing for font/asset paths) and
	// re-renders when style overrides change — same as BlockCanvas uses internally.
	__unstableEditorStyles as EditorStyles,
} from '@wordpress/block-editor';
import {
	ToolbarButton,
	PanelBody,
	Notice,
	Button,
	Spinner,
	Modal,
	SlotFillProvider,
} from '@wordpress/components';
import { useEntityBlockEditor } from '@wordpress/core-data';
import { useSelect, useDispatch, select as selectStore } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { useRef, useEffect, useState, createContext, useContext, useCallback, createPortal } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import megaMenuIconSvgRaw from '../images/mega-menu-icon.svg?raw';


/**
 * Inline SVG icon components — avoids a wp-primitives/wp-icons dependency at module-init time.
 */
const ArrowLeftIcon = () => (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
		<path d="M20 11.2H6.8l3.7-3.7-1-1L3.9 12l5.6 5.5 1-1-3.7-3.7H20z" fill="currentColor" />
	</svg>
);

const EditIcon = () => (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
		<path d="m19 7-3-3-8.5 8.5-1 4 4-1L19 7Zm-7 11.5H5V20h7v-1.5Z" fill="currentColor" />
	</svg>
);

const UndoIcon = () => (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
		<path d="M18.3 11.7c-.6-.6-1.4-.9-2.3-.9H6.7l2.9-3.3-1.1-1-4.5 5L8.5 16l1-1-2.7-2.7H16c.5 0 .9.2 1.3.5 1 1 1 3.4 1 4.5v.3h1.5v-.2c0-1.5 0-4.3-1.5-5.7z" fill="currentColor" />
	</svg>
);

const RedoIcon = () => (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
		<path d="M15.6 6.5l-1.1 1 2.9 3.3H8c-.9 0-1.7.3-2.3.9-1.4 1.5-1.4 4.2-1.4 5.6v.2h1.5v-.3c0-1.1 0-3.5 1-4.5.3-.3.7-.5 1.3-.5h9.2L14.5 15l1.1 1.1 4.6-4.6-4.6-5z" fill="currentColor" />
	</svg>
);

const ListViewIcon = () => (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
		<path d="M3 6h11v1.5H3V6Zm3.5 5.5h11V13h-11v-1.5ZM21 17H10v1.5h11V17Z" fill="currentColor" />
	</svg>
);

const CloseSmallIcon = () => (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
		<path d="M12 13.06l3.712 3.713 1.061-1.06L13.061 12l3.712-3.712-1.06-1.06L12 10.938 8.288 7.227l-1.061 1.06L10.939 12l-3.712 3.712 1.06 1.061L12 13.061z" fill="currentColor" />
	</svg>
);

const PlusIcon = () => (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
		<path d="M11 12.5V17.5H12.5V12.5H17.5V11H12.5V6H11V11H6V12.5H11Z" fill="currentColor" />
	</svg>
);

const SettingsIcon = () => (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
		<path d="m19 7.5h-7.628c-.3089-.87389-1.1423-1.5-2.122-1.5-.97966 0-1.81309.62611-2.12197 1.5h-2.12803v1.5h2.12803c.30888.87389 1.14231 1.5 2.12197 1.5.9797 0 1.8131-.62611 2.122-1.5h7.628z" fill="currentColor" />
		<path d="m19 15h-2.128c-.3089-.8739-1.1423-1.5-2.122-1.5s-1.8131.6261-2.122 1.5h-7.628v1.5h7.628c.3089.8739 1.1423 1.5 2.122 1.5s1.8131-.6261 2.122-1.5h2.128z" fill="currentColor" />
	</svg>
);

const CloseIcon = () => (
	<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
		<path d="M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z" fill="currentColor" />
	</svg>
);

/**
 * Custom Mega Menu icon component - loads SVG from file
 * This ensures the icon updates automatically when the SVG file is modified.
 */
const MegaMenuIcon = ( { className } ) => {
	let svgContent = '';
	if ( typeof megaMenuIconSvgRaw === 'string' ) {
		svgContent = megaMenuIconSvgRaw;
	} else if ( megaMenuIconSvgRaw && typeof megaMenuIconSvgRaw === 'object' ) {
		svgContent = megaMenuIconSvgRaw.default || megaMenuIconSvgRaw.toString();
	} else {
		svgContent = String( megaMenuIconSvgRaw || '' );
	}

	svgContent = svgContent.trim();

	if ( ! svgContent || ! svgContent.startsWith( '<svg' ) ) {
		return (
			<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
				<path d="M 1 0.99 L 23 0.99 L 23 4.99 L 1 4.99 L 1 0.99 Z M 10 4.99 L 12 7.99 L 14 4.99 L 10 4.99 Z M 2 8.99 L 22 8.99 L 22 22.99 L 2 22.99 L 2 8.99 Z M 9.979 13.959 L 9.979 18.459 L 10.979 18.459 L 10.979 15.259 L 11.979 16.659 L 12.979 15.259 L 12.979 18.459 L 13.979 18.459 L 13.979 13.959 L 12.765 13.959 L 11.979 15.125 L 11.271 13.959 L 9.979 13.959 Z" fill="currentColor"/>
			</svg>
		);
	}

	const svgWithClassName = svgContent.replace(
		/<svg\s+([^>]*)>/i,
		( match, attrs ) => {
			const attrsWithoutClass = attrs.replace( /\s*class\s*=\s*["'][^"']*["']/i, '' );
			return `<svg ${ attrsWithoutClass.trim() } class="${ className }">`;
		}
	);

	return (
		<span
			dangerouslySetInnerHTML={ { __html: svgWithClassName } }
			style={ {
				display: 'inline-block',
				lineHeight: 0,
				verticalAlign: 'middle',
			} }
		/>
	);
};

/**
 * Registry of callbacks for programmatically opening the Mega Menu editor.
 * Key: mega-menu block clientId → Value: () => void (calls setIsEditing(true))
 * Allows the nav-link inspector "Edit Mega Menu" button to trigger the modal.
 */
const megaMenuOpenCallbacks = new Map();

/**
 * Recursively search a block tree for a mega-menu-block/mega-menu block.
 * Returns the clientId of the first match, or null.
 *
 * @param {Object} block Root block object to search.
 * @return {string|null} clientId of the mega menu block, or null.
 */
function findMegaMenuBlock( block ) {
	if ( ! block ) return null;
	if ( block.name === 'mega-menu-block/mega-menu' ) return block.clientId;
	for ( const innerBlock of block.innerBlocks ?? [] ) {
		const found = findMegaMenuBlock( innerBlock );
		if ( found ) return found;
	}
	return null;
}

/**
 * Context flag that signals inner blocks are rendering inside the Mega Menu editor.
 * Used by enhanceNavigationBlockEdit to inject overlayMenu: 'never' even when
 * the global block-editor store can't be used to detect the parent chain.
 */
const MegaMenuEditorContext = createContext( false );

/**
 * Build a fresh set of default block objects for the 3-column Mega Menu template.
 *
 * @return {Array} Array of block objects.
 */
function buildDefaultBlocks() {
	return [
		createBlock( 'core/columns', {}, [
			createBlock( 'core/column', {
				className: 'mega-menu__intro',
			}, [
				createBlock( 'core/heading', { level: 3, placeholder: 'Section heading…' } ),
				createBlock( 'core/paragraph', { placeholder: 'Brief description of this section.' } ),
			] ),
			createBlock( 'core/column', {
				className: 'mega-menu__primary',
			}, [
				createBlock( 'core/paragraph', { placeholder: 'Add content here…' } ),
			] ),
			createBlock( 'core/column', {
				className: 'mega-menu__secondary',
			}, [
				createBlock( 'core/paragraph', { placeholder: 'Add links or content here…' } ),
			] ),
		] ),
	];
}

/**
 * An <iframe>-based canvas for editing mega menu blocks with full theme-style isolation.
 *
 * Why iframe instead of injecting <style> into the parent document:
 *   • WP's settings.styles CSS is authored for an iframe context where `body` IS the
 *     canvas root. In a `<div>`, selectors like `body { font-family }` hit the admin
 *     page, not the canvas; selectors like `body.editor-styles-wrapper` never match a
 *     div at all. An iframe avoids all of this without PostCSS/transformStyles.
 *
 * Style injection strategy (mirrors WP's own Iframe component):
 *   1. settings.__unstableResolvedAssets.styles — HTML string of <link>/<style> tags for
 *      wp-block-library and other block assets. WP generates this server-side.
 *   2. Compatibility styles — CSS text read synchronously from document.styleSheets
 *      cssRules (avoiding async <link> clones that race with portal mounting).
 *   3. settings.styles are injected dynamically by EditorStyles rendered inside the portal
 *      (same mechanism as WP's BlockCanvas), which runs transformStyles for baseURL rebasing.
 *
 * The blob URL HTML is intentionally minimal: basic reset + resolvedAssets link tags.
 * All settings.styles injection is handled by the EditorStyles child component so that
 * URL rebasing (for font faces, background images) and dynamic overrides work correctly.
 *
 * @param {Object}       props
 * @param {Object}       props.resolvedAssets  settings.__unstableResolvedAssets.
 * @param {Object}       props.contentRef      Ref to forward to the iframe body (used by BlockTools).
 * @param {ReactElement} props.children        Block tree (+ EditorStyles) to portal into the iframe.
 */
function MegaMenuCanvasIframe( { resolvedAssets, contentRef, children } ) {
	const [ mountEl, setMountEl ] = useState( null );

	// Build the iframe Blob URL once on mount. Keep it minimal: compat styles for
	// block-editor context + resolvedAssets link tags for the block library CSS.
	// settings.styles are handled at runtime by the EditorStyles child component.
	const srcRef = useRef( null );
	if ( ! srcRef.current ) {
		// 1. Collect compat styles from cssRules (synchronous text, no async link fetches).
		//    Filters to sheets that contain .editor-styles-wrapper or .wp-block selectors
		//    so we only carry over block-editor-relevant admin CSS.
		const compatParts = [];
		Array.from( document.styleSheets ).forEach( ( sheet ) => {
			try { sheet.cssRules; } catch ( e ) { return; }
			const { cssRules } = sheet;
			if ( ! cssRules ) return;

			const hasBlockSelector = Array.from( cssRules ).some( function checkRule( rule ) {
				if ( rule.cssRules ) return Array.from( rule.cssRules ).some( checkRule );
				return rule.selectorText && (
					rule.selectorText.includes( '.editor-styles-wrapper' ) ||
					rule.selectorText.includes( '.wp-block' )
				);
			} );
			if ( ! hasBlockSelector ) return;

			const cssText = Array.from( cssRules )
				.map( ( r ) => { try { return r.cssText; } catch ( e ) { return ''; } } )
				.filter( Boolean )
				.join( '\n' );
			if ( cssText ) compatParts.push( cssText );
		} );

		// 2. __unstableResolvedAssets.styles is the HTML string WP uses to load block
		//    library assets (wp-block-library etc.) into its own editor iframe.
		const resolvedHtml = resolvedAssets?.styles ?? '';

		const html = [
			'<!doctype html><html><head><meta charset="utf-8">',
			'<style>',
			'html,body{margin:0;padding:0;}',
			'body{box-sizing:border-box;overflow-x:hidden;',
			'     padding:48px max(24px,calc((100% - 860px)/2));}',
			'.block-editor-block-list__layout{min-height:200px;}',
			'</style>',
			resolvedHtml,
			compatParts.length ? `<style>\n${ compatParts.join( '\n' ) }\n</style>` : '',
			'</head>',
			'<body class="wp-site-blocks editor-styles-wrapper"></body>',
			'</html>',
		].join( '' );

		srcRef.current = URL.createObjectURL( new Blob( [ html ], { type: 'text/html' } ) );
	}

	// Revoke the blob URL on unmount to prevent memory leaks.
	useEffect( () => () => URL.revokeObjectURL( srcRef.current ), [] ); // eslint-disable-line react-hooks/exhaustive-deps

	const handleLoad = useCallback( ( e ) => {
		const frame = e.target;
		const doc = frame.contentDocument;
		if ( ! doc ) return;

		// Expose iframe body via the forwarded ref so BlockTools can use it.
		if ( contentRef ) {
			contentRef.current = doc.body;
		}

		// Bubble keyboard events from iframe → parent so WP shortcuts (Ctrl+Z etc.) work.
		doc.addEventListener( 'keydown', ( ev ) => {
			try {
				frame.dispatchEvent( new KeyboardEvent( ev.type, {
					bubbles: true,
					cancelable: ev.cancelable,
					key: ev.key, code: ev.code, keyCode: ev.keyCode, which: ev.which,
					ctrlKey: ev.ctrlKey, metaKey: ev.metaKey, shiftKey: ev.shiftKey, altKey: ev.altKey,
					repeat: ev.repeat,
				} ) );
			} catch ( err ) { /* noop */ }
		} );

		setMountEl( doc.body );
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	return (
		<>
			<iframe
				src={ srcRef.current }
				onLoad={ handleLoad }
				style={ { border: 'none', display: 'block', width: '100%' } }
				title={ __( 'Mega Menu content area', 'mega-menu-block' ) }
			/>
			{ mountEl && createPortal( children, mountEl ) }
		</>
	);
}

/**
 * Isolated editor rendered inside the Modal.
 * BlockEditorProvider creates its own sub-registry so edits never touch
 * the outer Navigation block's entity subscription.
 *
 * leftPanel: 'overview' | 'inserter' | null
 */
function MegaMenuBlockEditor( { blocks, onInput, onChange, onClose, parentLabel, entityId } ) {
	const settingsRef = useRef( null );
	if ( ! settingsRef.current ) {
		settingsRef.current = {
			...selectStore( 'core/block-editor' ).getSettings(),
			// Always use floating toolbar inside the modal regardless of the user's
			// global "Top Toolbar" preference. When hasFixedToolbar:true, BlockTools
			// renders no toolbar slot at all — the FSE's own fixed toolbar appears
			// behind our modal instead.
			hasFixedToolbar: false,
		};
	}
	const canvasRef = useRef( null );
	const safeBlocks = Array.isArray( blocks ) ? blocks : [];
	const [ leftPanel, setLeftPanel ] = useState( 'overview' );
	const [ showInspector, setShowInspector ] = useState( true );

	const { undo, redo } = useDispatch( 'core' );
	const { canUndo, canRedo, isDirty } = useSelect( ( select ) => ( {
		canUndo: select( 'core' ).hasUndo?.() ?? false,
		canRedo: select( 'core' ).hasRedo?.() ?? false,
		isDirty: entityId
			? ( select( 'core' ).hasEditsForEntityRecord?.( 'postType', 'wp_block', entityId ) ?? false )
			: false,
	} ), [ entityId ] );

	return (
		<SlotFillProvider>
		<MegaMenuEditorContext.Provider value={ true }>
			<BlockEditorProvider
				value={ safeBlocks }
				onInput={ onInput }
				onChange={ onChange }
				settings={ settingsRef.current }
			>
				<div className="wp-block-mega-menu__editor">

					{ /* ── Toolbar ── */ }
					<div className="wp-block-mega-menu__editor-toolbar">
						<div className="wp-block-mega-menu__toolbar-left">
							<Button
								icon={ ArrowLeftIcon }
								label={ __( 'Close editor', 'mega-menu-block' ) }
								onClick={ onClose }
								className="wp-block-mega-menu__toolbar-btn"
							/>
							<div className="wp-block-mega-menu__context">
								{ parentLabel && (
									<>
										<span className="wp-block-mega-menu__context-parent">
											{ parentLabel }
										</span>
										<span className="wp-block-mega-menu__context-sep" aria-hidden="true">
											{ '›' }
										</span>
									</>
								) }
								<span className="wp-block-mega-menu__context-current">
									{ __( 'Mega Menu', 'mega-menu-block' ) }
								</span>
							</div>
						</div>

						<div className="wp-block-mega-menu__toolbar-center">
							<Button
								icon={ PlusIcon }
								label={ __( 'Toggle block inserter', 'mega-menu-block' ) }
								onClick={ () => setLeftPanel( leftPanel === 'inserter' ? null : 'inserter' ) }
								isPressed={ leftPanel === 'inserter' }
								className="wp-block-mega-menu__toolbar-btn wp-block-mega-menu__toolbar-btn--inserter"
							/>
							<Button
								icon={ UndoIcon }
								label={ __( 'Undo', 'mega-menu-block' ) }
								onClick={ undo }
								disabled={ ! canUndo }
								className="wp-block-mega-menu__toolbar-btn"
							/>
							<Button
								icon={ RedoIcon }
								label={ __( 'Redo', 'mega-menu-block' ) }
								onClick={ redo }
								disabled={ ! canRedo }
								className="wp-block-mega-menu__toolbar-btn"
							/>
							<Button
								icon={ ListViewIcon }
								label={ __( 'Document Overview', 'mega-menu-block' ) }
								onClick={ () => setLeftPanel( leftPanel === 'overview' ? null : 'overview' ) }
								isPressed={ leftPanel === 'overview' }
								className="wp-block-mega-menu__toolbar-btn"
							/>
						</div>

						<div className="wp-block-mega-menu__toolbar-right">
							{ isDirty && (
								<span className="wp-block-mega-menu__unsaved">
									{ __( 'Unsaved — click ← then "Save" in the FSE to persist', 'mega-menu-block' ) }
								</span>
							) }
							<Button
								icon={ SettingsIcon }
								label={ __( 'Toggle block settings', 'mega-menu-block' ) }
								onClick={ () => setShowInspector( ( v ) => ! v ) }
								isPressed={ showInspector }
								className="wp-block-mega-menu__toolbar-btn"
							/>
							<Button
								icon={ CloseIcon }
								label={ __( 'Close editor', 'mega-menu-block' ) }
								onClick={ onClose }
								className="wp-block-mega-menu__toolbar-btn"
							/>
						</div>
					</div>

					{ /* ── Body ── */ }
					<div className="wp-block-mega-menu__editor-body">

						{ /* Block inserter library — renders inline so it stays inside the modal */ }
						{ leftPanel === 'inserter' && InserterLibrary && (
							<div className="wp-block-mega-menu__editor-inserter">
								<InserterLibrary
									onSelect={ () => setLeftPanel( null ) }
									onClose={ () => setLeftPanel( null ) }
									rootClientId={ undefined }
									isAppender={ false }
								/>
							</div>
						) }

						{ /* Document overview (list view) */ }
						{ leftPanel === 'overview' && (
							<div className="wp-block-mega-menu__editor-list-view">
								<div className="wp-block-mega-menu__list-view-header">
									<span className="wp-block-mega-menu__list-view-title">
										{ __( 'Document Overview', 'mega-menu-block' ) }
									</span>
									<Button
										icon={ CloseSmallIcon }
										label={ __( 'Close Document Overview', 'mega-menu-block' ) }
										onClick={ () => setLeftPanel( null ) }
										className="wp-block-mega-menu__toolbar-btn wp-block-mega-menu__toolbar-btn--light"
									/>
								</div>
								<div className="wp-block-mega-menu__list-view-content">
									<ListView />
								</div>
							</div>
						) }

						{ /* Canvas — an iframe gives true CSS isolation so theme styles apply to
						     content without leaking into the admin chrome or needing
						     PostCSS/transformStyles. */ }
						<div className="wp-block-mega-menu__editor-canvas">
							<BlockTools __unstableContentRef={ canvasRef }>
								<MegaMenuCanvasIframe
									resolvedAssets={ settingsRef.current?.__unstableResolvedAssets }
									contentRef={ canvasRef }
								>
									{ /* EditorStyles mirrors what BlockCanvas does: reads settings.styles
									     from the store, runs transformStyles (handles baseURL rebasing
									     for font faces / background images), and injects <style> tags
									     dynamically. This is why styles match the main FSE canvas. */ }
									<EditorStyles styles={ settingsRef.current?.styles } />
									<WritingFlow>
										<ObserveTyping>
											<BlockList />
										</ObserveTyping>
									</WritingFlow>
								</MegaMenuCanvasIframe>
							</BlockTools>
						</div>

						{ /* Inspector — absolutely positioned so overflow-y scroll is bounded */ }
						{ showInspector && (
							<div className="wp-block-mega-menu__editor-inspector">
								<div className="wp-block-mega-menu__inspector-inner">
									<BlockInspector />
								</div>
							</div>
						) }
					</div>

					{ /* ── Footer / breadcrumb ── */ }
					<div className="wp-block-mega-menu__editor-footer">
						<BlockBreadcrumb />
					</div>
				</div>
			</BlockEditorProvider>
		</MegaMenuEditorContext.Provider>
		</SlotFillProvider>
	);
}

/**
 * Loads the wp_block entity and renders the isolated editor.
 * Kept as a separate component so useEntityBlockEditor is only ever called
 * when a valid entityId exists — never with undefined.
 */
function MegaMenuEntityEditor( { entityId, onClose, parentLabel } ) {
	const [ blocks, onInput, onChange ] = useEntityBlockEditor( 'postType', 'wp_block', { id: entityId } );

	if ( ! Array.isArray( blocks ) ) {
		return (
			<div style={ { display: 'flex', justifyContent: 'center', padding: '3em' } }>
				<Spinner />
			</div>
		);
	}

	return (
		<MegaMenuBlockEditor
			blocks={ blocks }
			onInput={ onInput }
			onChange={ onChange }
			onClose={ onClose }
			parentLabel={ parentLabel }
			entityId={ entityId }
		/>
	);
}

/**
 * Register the Mega Menu block.
 */
registerBlockType( 'mega-menu-block/mega-menu', {
	icon: MegaMenuIcon,
	edit: ( { attributes, setAttributes, clientId } ) => {
		const { ref } = attributes;
		const blockProps = useBlockProps( { className: 'wp-block-mega-menu' } );

		// Detect legacy inline inner blocks (stored in the navigation post, no ref attribute).
		const legacyInnerBlocks = useSelect(
			( select ) => select( 'core/block-editor' ).getBlocks( clientId ),
			[ clientId ]
		);

		// Resolve the nearest ancestor nav-link/submenu label for editor context display.
		// Nav-link labels are stored as HTML, so decode entities (e.g. &amp; → &)
		// before displaying as plain text. Using a textarea is the DOM-safe way to
		// decode without risking XSS from script tags.
		const parentLabel = useSelect( ( select ) => {
			const { getBlockParents, getBlock } = select( 'core/block-editor' );
			const parents = getBlockParents( clientId, true );
			for ( const parentId of parents ) {
				const block = getBlock( parentId );
				if (
					block?.name === 'core/navigation-link' ||
					block?.name === 'core/navigation-submenu'
				) {
					const raw = block.attributes?.label || block.attributes?.title || '';
					if ( ! raw ) return '';
					const t = document.createElement( 'textarea' );
					t.innerHTML = raw;
					return t.value;
				}
			}
			return '';
		}, [ clientId ] );

		const initialHasLegacyRef = useRef( null );
		if ( initialHasLegacyRef.current === null ) {
			initialHasLegacyRef.current = legacyInnerBlocks.length > 0;
		}

		const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );
		const [ isEditing, setIsEditing ] = useState( false );
		const [ isCreating, setIsCreating ] = useState( false );
		const [ createError, setCreateError ] = useState( false );
		const [ isMigrating, setIsMigrating ] = useState( false );
		const createAttemptedRef = useRef( false );

		// Allow external callers (e.g. the nav-link inspector button) to open this editor.
		useEffect( () => {
			megaMenuOpenCallbacks.set( clientId, () => setIsEditing( true ) );
			return () => megaMenuOpenCallbacks.delete( clientId );
		}, [ clientId ] );

		// On mount: auto-create a private wp_block entity for brand-new blocks.
		useEffect( () => {
			if ( createAttemptedRef.current || ref || initialHasLegacyRef.current ) {
				return;
			}
			createAttemptedRef.current = true;
			setIsCreating( true );

			apiFetch( {
				path: '/wp/v2/blocks',
				method: 'POST',
				data: {
					title: __( 'Mega Menu', 'mega-menu-block' ),
					content: serialize( buildDefaultBlocks() ),
					status: 'private',
				},
			} ).then( ( response ) => {
				if ( response?.id ) {
					setAttributes( { ref: response.id } );
				} else {
					setCreateError( true );
					setIsCreating( false );
				}
			} ).catch( () => {
				setCreateError( true );
				setIsCreating( false );
			} );
		}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

		// Migrate legacy inline inner blocks to a wp_block entity.
		const handleMigrate = () => {
			if ( isMigrating || legacyInnerBlocks.length === 0 ) {
				return;
			}
			setIsMigrating( true );

			apiFetch( {
				path: '/wp/v2/blocks',
				method: 'POST',
				data: {
					title: __( 'Mega Menu', 'mega-menu-block' ),
					content: serialize( legacyInnerBlocks ),
					status: 'private',
				},
			} ).then( ( response ) => {
				if ( response?.id ) {
					setAttributes( { ref: response.id } );
					replaceInnerBlocks( clientId, [] );
				} else {
					setIsMigrating( false );
				}
			} ).catch( () => {
				setIsMigrating( false );
			} );
		};

		// ── Loading state while creating the entity ──────────────────────────
		if ( ! ref && isCreating ) {
			return (
				<div
					{ ...blockProps }
					style={ { alignItems: 'center', display: 'flex', gap: '0.5em', padding: '1em' } }
				>
					<Spinner />
					<span>{ __( 'Setting up Mega Menu…', 'mega-menu-block' ) }</span>
				</div>
			);
		}

		// ── Error state ───────────────────────────────────────────────────────
		if ( ! ref && createError ) {
			return (
				<div { ...blockProps }>
					<Notice status="error" isDismissible={ false }>
						{ __(
							'Failed to initialize Mega Menu. Please remove and re-insert this block.',
							'mega-menu-block'
						) }
					</Notice>
				</div>
			);
		}

		// ── Legacy mode: has inline inner blocks, no ref ──────────────────────
		if ( ! ref && legacyInnerBlocks.length > 0 ) {
			return (
				<>
					<InspectorControls>
						<PanelBody
							title={ __( 'Mega Menu Storage', 'mega-menu-block' ) }
							initialOpen
						>
							<Notice status="warning" isDismissible={ false }>
								{ __(
									'This Mega Menu uses legacy inline storage, which causes focus-jumping when editing. Upgrade to enable modal editing without focus issues.',
									'mega-menu-block'
								) }
							</Notice>
							<Button
								variant="primary"
								onClick={ handleMigrate }
								isBusy={ isMigrating }
								disabled={ isMigrating }
								style={ { marginTop: '8px' } }
							>
								{ isMigrating
									? __( 'Upgrading…', 'mega-menu-block' )
									: __( 'Upgrade Mega Menu', 'mega-menu-block' ) }
							</Button>
						</PanelBody>
					</InspectorControls>
					<div { ...blockProps }>
						<div className="wp-block-mega-menu__legacy-notice">
							{ __( 'Legacy Mega Menu — please upgrade in Block Settings', 'mega-menu-block' ) }
						</div>
					</div>
				</>
			);
		}

		// ── Entity mode — placeholder in the Navigation, editor as fullscreen overlay ──
		return (
			<>
				{ /* Placeholder shown inside the Navigation block */ }
				<div { ...blockProps }>
					<div className="wp-block-mega-menu__placeholder">
						<span className="wp-block-mega-menu__placeholder-icon">
							<MegaMenuIcon />
						</span>
						<span className="wp-block-mega-menu__placeholder-label">
							{ __( 'Mega Menu', 'mega-menu-block' ) }
						</span>
						<Button
							variant="primary"
							icon={ EditIcon }
							onClick={ () => setIsEditing( true ) }
							className="wp-block-mega-menu__edit-button"
						>
							{ __( 'Edit Mega Menu', 'mega-menu-block' ) }
						</Button>
					</div>
				</div>

				{ isEditing && ref && (
					<Modal
						title={ __( 'Edit Mega Menu', 'mega-menu-block' ) }
						onRequestClose={ () => setIsEditing( false ) }
						className="wp-block-mega-menu__editor-modal"
						size="fill"
						shouldCloseOnClickOutside={ false }
					>
						<MegaMenuEntityEditor
							entityId={ ref }
							onClose={ () => setIsEditing( false ) }
							parentLabel={ parentLabel }
						/>
					</Modal>
				) }
			</>
		);
	},

	save: ( { attributes } ) => {
		// Entity mode: content lives in the wp_block post, not inline.
		if ( attributes.ref ) {
			return null;
		}
		// Legacy mode: inner blocks serialised inline for backwards compatibility.
		return <InnerBlocks.Content />;
	},
} );

/**
 * Check if a Navigation block is inside a Mega Menu (or any child of a Mega Menu).
 *
 * @param {string}   clientId        The Navigation block's client ID.
 * @param {Function} getBlock        Get block function from store.
 * @param {Function} getBlockParents Get block parents function from store.
 * @return {boolean} True if nested inside a Mega Menu block.
 */
function isInsideMegaMenu( clientId, getBlock, getBlockParents ) {
	if ( ! clientId ) {
		return false;
	}
	const parentClientIds = getBlockParents( clientId, true );
	if ( ! parentClientIds || parentClientIds.length === 0 ) {
		return false;
	}
	for ( let i = 0; i < parentClientIds.length; i++ ) {
		const block = getBlock( parentClientIds[ i ] );
		if ( block && block.name === 'mega-menu-block/mega-menu' ) {
			return true;
		}
	}
	return false;
}

/**
 * Filter Navigation block edit component to enforce overlayMenu: 'never'.
 *
 * Hooks are called unconditionally at the top — before the early return for
 * non-Navigation blocks — to satisfy React's Rules of Hooks.
 */
function enhanceNavigationBlockEdit( BlockEdit ) {
	return ( props ) => {
		// Hooks must come before any conditional returns.
		const isInMegaMenuEditor = useContext( MegaMenuEditorContext );
		const isInMegaMenuRef = useRef( null );

		if ( props.name !== 'core/navigation' ) {
			return <BlockEdit { ...props } />;
		}

		if ( isInMegaMenuRef.current === null ) {
			const { getBlock, getBlockParents } = selectStore( 'core/block-editor' );
			isInMegaMenuRef.current = isInsideMegaMenu(
				props.clientId,
				getBlock,
				getBlockParents
			);
		}

		if ( ! isInMegaMenuEditor && ! isInMegaMenuRef.current ) {
			return <BlockEdit { ...props } />;
		}

		return (
			<BlockEdit
				{ ...props }
				attributes={ { ...props.attributes, overlayMenu: 'never' } }
			/>
		);
	};
}

/**
 * Add "Add Mega Menu" toolbar button and inspector panel to Navigation Link / Submenu blocks.
 *
 * All hooks are called unconditionally at the top — before any early returns —
 * to satisfy React's Rules of Hooks.
 */
function addMegaMenuToolbarButton( BlockEdit ) {
	return ( props ) => {
		// All hooks unconditionally at top (Rules of Hooks).
		const { insertBlock } = useDispatch( 'core/block-editor' );
		const isTargetBlock = props.name === 'core/navigation-link' || props.name === 'core/navigation-submenu';

		const megaMenuClientId = useSelect( ( select ) => {
			if ( ! isTargetBlock ) return null;
			const block = select( 'core/block-editor' ).getBlock( props.clientId );
			return findMegaMenuBlock( block );
		}, [ isTargetBlock, props.clientId ] );

		if ( ! isTargetBlock ) {
			return <BlockEdit { ...props } />;
		}

		const { clientId } = props;

		const handleAddOrOpenMegaMenu = () => {
			if ( megaMenuClientId ) {
				// Prefer the registered callback so the modal opens immediately.
				const openFn = megaMenuOpenCallbacks.get( megaMenuClientId );
				if ( openFn ) {
					openFn();
					return;
				}
			}
			// Create a new mega menu child block if none exists.
			const megaMenuBlock = createBlock( 'mega-menu-block/mega-menu' );
			insertBlock( megaMenuBlock, undefined, clientId );
		};

		return (
			<>
				<BlockEdit { ...props } />
				<BlockControls group="block">
					<ToolbarButton
						icon={ MegaMenuIcon }
						label={ megaMenuClientId ? __( 'Edit Mega Menu', 'mega-menu-block' ) : __( 'Add Mega Menu', 'mega-menu-block' ) }
						onClick={ handleAddOrOpenMegaMenu }
					/>
				</BlockControls>
				{ megaMenuClientId && (
					<InspectorControls>
						<PanelBody title={ __( 'Mega Menu', 'mega-menu-block' ) } initialOpen={ false }>
							<Button
								variant="primary"
								onClick={ handleAddOrOpenMegaMenu }
								style={ { justifyContent: 'center', width: '100%' } }
							>
								{ __( 'Edit Mega Menu', 'mega-menu-block' ) }
							</Button>
						</PanelBody>
					</InspectorControls>
				) }
			</>
		);
	};
}

// Register filters.
addFilter( 'editor.BlockEdit', 'mega-menu-block/add-mega-menu-toolbar', addMegaMenuToolbarButton );
addFilter( 'editor.BlockEdit', 'mega-menu-block/navigation-menu-filter', enhanceNavigationBlockEdit );
