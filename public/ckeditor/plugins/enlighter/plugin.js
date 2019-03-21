/**
 * Copyright (c) 2014-2018, CKSource - Frederico Knabben. All rights reserved.
 * Licensed under the terms of the MIT License (see LICENSE.md).
 *
 * Basic sample plugin inserting current date and time into the CKEditor editing area.
 *
 * Created out of the CKEditor Plugin SDK:
 * http://docs.ckeditor.com/ckeditor4/docs/#!/guide/plugin_sdk_intro
 */
// Register the plugin within the editor.
CKEDITOR.plugins.add( 'enlighter', {

	// Register the icons. They must match command names.
	icons: 'enlighter',

	// The plugin initialization logic goes inside this method.
	init: function( editor ) {
    var pluginDirectory = this.path;
    editor.addContentsCss( pluginDirectory + 'styles/editor.css' );

		// Define the editor command that inserts a timestamp.
    CKEDITOR.dialog.add( 'enlighterDialog', this.path + 'dialogs/enlighter.js' );
    editor.addCommand( 'insertEnlighterCode', new CKEDITOR.dialogCommand( 'enlighterDialog' ) );

    if ( editor.contextMenu ) {
      editor.addMenuGroup( 'enlighterGroup' );
      editor.addMenuItem( 'enlighterItem', {
        label: 'Edit code',
        icon: this.path + 'icons/enlighter.png',
        command: 'insertEnlighterCode',
        group: 'enlighterGroup'
      });
      editor.contextMenu.addListener( function( element ) {
        if ( element.getAscendant( 'pre', true ) || element.getAscendant( 'code', true ) ) {
          return { enlighterItem: CKEDITOR.TRISTATE_OFF };
        }
      });
    }

		// Create the toolbar button that executes the above command.
		editor.ui.addButton( 'Enlighter', {
			label: 'Inert code',
			command: 'insertEnlighterCode',
			toolbar: 'insert'
		});
	}
});
