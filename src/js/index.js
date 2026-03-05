/**
 * Mega Menu Block Editor Script
 *
 * @package MegaMenuBlock
 */

import { registerBlockType, createBlock } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks, useInnerBlocksProps, BlockControls } from '@wordpress/block-editor';
import { ToolbarButton, ToolbarGroup } from '@wordpress/components';
import { useSelect, useDispatch, select as selectStore, dispatch as dispatchStore } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import { useEffect, useRef, useLayoutEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
// Import SVG as raw text using raw-loader
// raw-loader returns the file content as a string (or as default export)
import megaMenuIconSvgRaw from '../images/mega-menu-icon.svg?raw';

/**
 * Custom Mega Menu icon component - loads SVG from file
 * This ensures the icon updates automatically when the SVG file is modified.
 */
const MegaMenuIcon = ( { className } ) => {
	// raw-loader with esModule: false returns the content directly as a string
	// raw-loader with esModule: true returns { default: string }
	let svgContent = '';
	if ( typeof megaMenuIconSvgRaw === 'string' ) {
		// Direct string (raw-loader with esModule: false)
		svgContent = megaMenuIconSvgRaw;
	} else if ( megaMenuIconSvgRaw && typeof megaMenuIconSvgRaw === 'object' ) {
		// Module with default export (raw-loader with esModule: true, or other format)
		svgContent = megaMenuIconSvgRaw.default || megaMenuIconSvgRaw.toString();
	} else {
		// Fallback: convert to string
		svgContent = String( megaMenuIconSvgRaw || '' );
	}

	// Clean up the SVG content - remove any extra whitespace/newlines
	svgContent = svgContent.trim();

	// Check if we got valid SVG content
	if ( ! svgContent || ! svgContent.startsWith( '<svg' ) ) {
		// Return a fallback icon that matches the SVG file structure
		return (
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M 1 0.99 L 23 0.99 L 23 4.99 L 1 4.99 L 1 0.99 Z M 10 4.99 L 12 7.99 L 14 4.99 L 10 4.99 Z M 2 8.99 L 22 8.99 L 22 22.99 L 2 22.99 L 2 8.99 Z M 9.979 13.959 L 9.979 18.459 L 10.979 18.459 L 10.979 15.259 L 11.979 16.659 L 12.979 15.259 L 12.979 18.459 L 13.979 18.459 L 13.979 13.959 L 12.765 13.959 L 11.979 15.125 L 11.271 13.959 L 9.979 13.959 Z" fill="currentColor"/>
      </svg>
		);
	}

	// Inject className into the SVG element
	const svgWithClassName = svgContent.replace(
		/<svg\s+([^>]*)>/i,
		( match, attrs ) => {
			// Remove existing class attribute if present
			const attrsWithoutClass = attrs.replace( /\s*class\s*=\s*["'][^"']*["']/i, '' );
			// Add our className
			return `<svg ${ attrsWithoutClass.trim() } class="${ className }">`;
		}
	);

	// Render the SVG using dangerouslySetInnerHTML
	// The SVG content should already be a valid HTML string from webpack
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
 * Register the Mega Menu block.
 */
registerBlockType( 'mega-menu-block/mega-menu', {
	icon: MegaMenuIcon,
	edit: ( { attributes, setAttributes, clientId } ) => {
		const blockProps = useBlockProps( {
			className: 'wp-block-mega-menu',
		} );

		// Get all registered blocks to allow any block inside.
		const allBlocks = useSelect( ( select ) => {
			return select( 'core/blocks' ).getBlockTypes();
		}, [] );

		// Get all block names for allowedBlocks, but exclude Mega Menu blocks.
		const allBlockNames = allBlocks
			? allBlocks
					.map( ( block ) => block.name )
					.filter( ( name ) => name !== 'mega-menu-block/mega-menu' )
			: null;

		// Use useInnerBlocksProps with a default paragraph template.
		const innerBlocksProps = useInnerBlocksProps( blockProps, {
			allowedBlocks: allBlockNames,
			templateLock: false,
			orientation: 'vertical',
			renderAppender: InnerBlocks.ButtonBlockAppender,
			// Default template with a paragraph block.
			template: [
				[ 'core/paragraph', { placeholder: 'Add content or type / to build mega menu...' } ],
			],
		} );

		return <div { ...innerBlocksProps } />;
	},

	save: () => {
		// For dynamic blocks, we use InnerBlocks.Content to save inner blocks.
		// Even though we use server-side rendering, we need to save the inner blocks
		// structure in the block content so they persist.
		return <InnerBlocks.Content />;
	},
} );

/**
 * Helper function to get the ancestor Navigation block and its menu ID.
 *
 * @param {string} clientId The current block's client ID.
 * @param {Function} getBlock Get block function from store.
 * @param {Function} getBlockParents Get block parents function from store.
 * @return {Object|null} Object with navigationBlock and menuId, or null if not found.
 */
function getAncestorNavigationBlock( clientId, getBlock, getBlockParents ) {
	if ( ! clientId ) {
		return null;
	}

	// Get all parent blocks.
	const parentClientIds = getBlockParents( clientId, true ); // true = include self

	if ( ! parentClientIds || parentClientIds.length === 0 ) {
		return null;
	}

	let foundMegaMenu = false;

	// Traverse up the block tree (parents are ordered from root to direct parent).
	for ( let i = parentClientIds.length - 1; i >= 0; i-- ) {
		const parentClientId = parentClientIds[ i ];
		const block = getBlock( parentClientId );

		if ( ! block ) {
			continue;
		}

		// Check if this is a Mega Menu block.
		if ( block.name === 'mega-menu-block/mega-menu' ) {
			foundMegaMenu = true;
			continue;
		}

		// Check if this is a Navigation block.
		if ( block.name === 'core/navigation' ) {
			const menuId = block.attributes?.ref || null;
			// If we found a Mega Menu before this Navigation block, return it.
			if ( foundMegaMenu && menuId ) {
				return {
					navigationBlock: block,
					menuId: menuId,
				};
			}
			// Reset foundMegaMenu if we hit a Navigation block before finding a Mega Menu.
			foundMegaMenu = false;
		}
	}

	return null;
}

/**
 * Check if a Navigation block is inside a Mega Menu (or any child of a Mega Menu).
 *
 * @param {string} clientId The Navigation block's client ID.
 * @param {Function} getBlock Get block function from store.
 * @param {Function} getBlockParents Get block parents function from store.
 * @return {boolean} True if nested inside a Mega Menu block.
 */
function isInsideMegaMenu( clientId, getBlock, getBlockParents ) {
	if ( ! clientId ) {
		return false;
	}

	// Get all parent blocks.
	const parentClientIds = getBlockParents( clientId, true ); // true = include self

	if ( ! parentClientIds || parentClientIds.length === 0 ) {
		return false;
	}

	// Check if any parent is a Mega Menu block.
	for ( let i = 0; i < parentClientIds.length; i++ ) {
		const parentClientId = parentClientIds[ i ];
		const block = getBlock( parentClientId );

		if ( block && block.name === 'mega-menu-block/mega-menu' ) {
			return true;
		}
	}

	return false;
}

/**
 * Check if a Navigation block is inside a Mega Menu that's nested in another Navigation block.
 *
 * @param {string} clientId The Navigation block's client ID.
 * @param {Function} getBlock Get block function from store.
 * @param {Function} getBlockParents Get block parents function from store.
 * @return {boolean} True if nested in Mega Menu > Navigation.
 */
function isInsideMegaMenuInNavigation( clientId, getBlock, getBlockParents ) {
	const ancestor = getAncestorNavigationBlock( clientId, getBlock, getBlockParents );
	return ancestor !== null;
}

/**
 * Get a safe default menu ID that's different from the ancestor menu.
 * First tries to reuse an existing unused menu, then creates a new one if needed.
 *
 * @param {number} ancestorMenuId The ancestor Navigation block's menu ID.
 * @param {Array} navigationMenus Available navigation menus.
 * @param {Function} createMenu Function to create a new menu.
 * @param {Function} getBlocks Function to get all blocks to check for menu usage.
 * @return {Promise<number>} A safe menu ID.
 */
async function getSafeDefaultMenu( ancestorMenuId, navigationMenus, createMenu, getBlocks ) {
	if ( ! navigationMenus || navigationMenus.length === 0 ) {
		// No menus available, create a new one.
		return await createMenu();
	}

	// Get all blocks to check which menus are currently in use.
	const allBlocks = getBlocks ? getBlocks() : [];
	const usedMenuIds = new Set();

	// Recursively find all Navigation blocks and their menu IDs.
	const findUsedMenus = ( blocks ) => {
		if ( ! blocks || ! Array.isArray( blocks ) ) {
			return;
		}
		blocks.forEach( ( block ) => {
			if ( block.name === 'core/navigation' && block.attributes?.ref ) {
				usedMenuIds.add( block.attributes.ref );
			}
			if ( block.innerBlocks ) {
				findUsedMenus( block.innerBlocks );
			}
		} );
	};

	findUsedMenus( allBlocks );

	// Find an unused menu that's not the ancestor menu.
	const unusedMenu = navigationMenus.find(
		( menu ) => menu.id !== ancestorMenuId && ! usedMenuIds.has( menu.id )
	);

	if ( unusedMenu ) {
		return unusedMenu.id;
	}

	// No unused menu available, create a new one.
	return await createMenu();
}

/**
 * Create a new navigation menu.
 *
 * @param {Object} dispatchObject Object with saveEntityRecord function.
 * @return {Promise<number|null>} The new menu's ID or null on failure.
 */
async function createNewMenu( dispatchObject ) {
	if ( ! dispatchObject || ! dispatchObject.saveEntityRecord ) {
		return null;
	}

	try {
		// Create menu with a Page Link as the default inner block instead of Paragraph.
		// Use a self-closing navigation-link block with proper attributes.
		const newMenu = await dispatchObject.saveEntityRecord( 'postType', 'wp_navigation', {
			title: 'Navigation Menu',
			status: 'publish',
			content:
				'<!-- wp:navigation-link {"label":"Page","type":"page","kind":"post-type","url":""} /-->',
		} );

		if ( newMenu && newMenu.id ) {
			return newMenu.id;
		}

		return null;
	} catch ( error ) {
		return null;
	}
}

/**
 * Filter Navigation block edit component to handle menu selection.
 *
 * @param {Function} BlockEdit Original block edit component.
 * @return {Function} Enhanced block edit component.
 */
function enhanceNavigationBlockEdit( BlockEdit ) {
	return ( props ) => {
		if ( props.name !== 'core/navigation' ) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes, clientId } = props;
		const { ref: menuId } = attributes;

		// Get block editor store functions.
		const { getBlock, getBlockParents } = useSelect( ( select ) => {
			const blockEditor = select( 'core/block-editor' );
			return {
				getBlock: blockEditor.getBlock.bind( blockEditor ),
				getBlockParents: blockEditor.getBlockParents.bind( blockEditor ),
			};
		}, [] );

		// Get available navigation menus.
		const allNavigationMenus = useSelect( ( select ) => {
			return select( 'core' ).getEntityRecords( 'postType', 'wp_navigation', {
				status: 'publish',
				per_page: -1,
			} );
		}, [] );

		// Get all blocks to check for menu usage.
		const allBlocks = useSelect( ( select ) => {
			return select( 'core/block-editor' ).getBlocks();
		}, [] );

		const { saveEntityRecord } = useDispatch( 'core' );

		// Check if this Navigation block is inside a Mega Menu (or any child of a Mega Menu).
		const isInMegaMenu = isInsideMegaMenu( clientId, getBlock, getBlockParents );

		// Check if there's an ancestor Navigation block with a menu.
		const ancestor = isInMegaMenu
			? getAncestorNavigationBlock( clientId, getBlock, getBlockParents )
			: null;

		const ancestorMenuId = ancestor ? ancestor.menuId : null;

		// Use a ref to track if we've already set the initial menu to avoid loops.
		const hasSetInitialMenu = useRef( false );
		// Track if this is the first render to maintain focus on initial insertion.
		const isFirstRender = useRef( true );

		// Get block editor dispatch to update attributes and maintain selection.
		const { updateBlockAttributes: updateBlockAttrs, selectBlock } = useDispatch(
			'core/block-editor'
		);

		// If inside Mega Menu and (no menu is set OR menu matches ancestor), create a new menu.
		// Use useLayoutEffect to run synchronously before paint.
		useLayoutEffect( () => {
			const needsNewMenu =
				isInMegaMenu &&
				( ! menuId || menuId === ancestorMenuId ) &&
				! hasSetInitialMenu.current;

			if ( needsNewMenu ) {
				hasSetInitialMenu.current = true;
				isFirstRender.current = false;
				// Try to reuse an existing unused menu, or create a new one if needed.
				getSafeDefaultMenu(
					ancestorMenuId,
					allNavigationMenus,
					() => createNewMenu( { saveEntityRecord } ),
					() => allBlocks
				)
					.then( ( newMenuId ) => {
						if ( newMenuId ) {
							// Update attributes including overlayMenu set to 'never' (off).
							// Update in a way that maintains focus on the block.
							updateBlockAttrs( clientId, {
								ref: newMenuId,
								overlayMenu: 'never',
							} );

							// Maintain focus on the Navigation block after update.
							// Use a small delay to ensure the update has processed.
							setTimeout( () => {
								selectBlock( clientId );
							}, 50 );
						}
					} )
					.catch( () => {
						// Silently handle errors.
					} );
			} else if ( isInMegaMenu && isFirstRender.current ) {
				// Even if menu is already set, maintain focus on first render.
				isFirstRender.current = false;
				// Ensure overlayMenu is set to 'never' if not already set.
				const currentOverlayMenu = attributes?.overlayMenu;
				if ( currentOverlayMenu !== 'never' ) {
					updateBlockAttrs( clientId, {
						overlayMenu: 'never',
					} );
				}
				// Maintain focus on the Navigation block.
				setTimeout( () => {
					selectBlock( clientId );
				}, 50 );
			}
		}, [
			isInMegaMenu,
			menuId,
			ancestorMenuId,
			clientId,
			getBlock,
			allBlocks,
			allNavigationMenus,
			updateBlockAttrs,
			selectBlock,
			saveEntityRecord,
			attributes?.overlayMenu,
		] );

		// Return the original BlockEdit component with all props intact.
		// We don't modify any props that would affect settings panels or rendering.
		return <BlockEdit { ...props } />;
	};
}

/**
 * Add "Add Mega Menu" toolbar button to Navigation Link and Navigation Submenu blocks.
 * This allows users to directly insert a Mega Menu block as a child, similar to "Add Submenu".
 *
 * @param {Function} BlockEdit Original block edit component.
 * @return {Function} Enhanced block edit component.
 */
function addMegaMenuToolbarButton( BlockEdit ) {
	return ( props ) => {
		// Only add button to Navigation Link and Navigation Submenu blocks.
		if ( props.name !== 'core/navigation-link' && props.name !== 'core/navigation-submenu' ) {
			return <BlockEdit { ...props } />;
		}

		const { clientId } = props;
		const { insertBlock, selectBlock } = useDispatch( 'core/block-editor' );

		// Get the current block to check for existing Mega Menu blocks.
		const currentBlock = useSelect( ( select ) => {
			const { getBlock } = select( 'core/block-editor' );
			return getBlock( clientId );
		}, [ clientId ] );

		// Helper function to find a Mega Menu block in inner blocks recursively.
		const findMegaMenuBlock = ( block ) => {
			if ( ! block ) {
				return null;
			}

			// Check if this block is a Mega Menu block.
			if ( block.name === 'mega-menu-block/mega-menu' ) {
				return block.clientId;
			}

			// Check inner blocks recursively.
			if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
				for ( const innerBlock of block.innerBlocks ) {
					const found = findMegaMenuBlock( innerBlock );
					if ( found ) {
						return found;
					}
				}
			}

			return null;
		};

		// Handler to insert Mega Menu block as a child of this Navigation Link/Submenu.
		const handleAddMegaMenu = () => {
			// Check if there's already a Mega Menu block in the inner blocks.
			const existingMegaMenuId = findMegaMenuBlock( currentBlock );

			if ( existingMegaMenuId ) {
				// If a Mega Menu block already exists, focus it instead of inserting a new one.
				selectBlock( existingMegaMenuId );
				return;
			}

			// Create the Mega Menu block.
			const megaMenuBlock = createBlock( 'mega-menu-block/mega-menu' );

			// Insert the Mega Menu block as a child of this Navigation Link/Submenu.
			// Navigation Links are stored in menu posts, but WordPress handles the insertion
			// automatically when we use insertBlock with the parent clientId.
			insertBlock( megaMenuBlock, undefined, clientId );
		};

		return (
			<>
				<BlockEdit { ...props } />
				<BlockControls group="block">
					<ToolbarButton
						icon={ MegaMenuIcon }
						label={ __( 'Add Mega Menu', 'mega-menu-block' ) }
						onClick={ handleAddMegaMenu }
					/>
				</BlockControls>
			</>
		);
	};
}

// Register filters.
addFilter( 'editor.BlockEdit', 'mega-menu-block/add-mega-menu-toolbar', addMegaMenuToolbarButton );
addFilter( 'editor.BlockEdit', 'mega-menu-block/navigation-menu-filter', enhanceNavigationBlockEdit );
