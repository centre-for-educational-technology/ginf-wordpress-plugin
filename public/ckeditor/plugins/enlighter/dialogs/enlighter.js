CKEDITOR.dialog.add( 'enlighterDialog', function ( editor ) {
  var languages = [
    ['Generic Highlighting', 'generic'],
    ['No Highlighting', 'no-highlight']
  ];

  // Please note that this one relies on Highlight.js plugin having set the value
  if (window.top.EnlighterJS_EditorConfig) {
    languages.length = 0;
    Object.keys(window.top.EnlighterJS_EditorConfig.languages).forEach(function(key) {
      languages.push([key, window.top.EnlighterJS_EditorConfig.languages[key]]);
    });
  }

  return {
    title: 'Enlighter.js',
    minWidth: 400,
    minHeight: 200,
    contents: [
      {
        id: 'tab-general',
        elements: [
          {
            type: 'select',
            id: 'language',
            label: 'Language',
            items: languages,
            default: 'generic',
            setup: function( element ) {
              this.setValue( element.data('enlighter-language') );
            },
            commit: function( element ) {
              element.data( 'enlighter-language', this.getValue() );
            }
          },
          {
            type: 'select',
            id: 'mode',
            label: 'Mode',
            items: [ [ 'Block-Code', 'block' ], [ 'Inline-Code', 'inline' ]],
            default: 'block',
            setup: function( element ) {
              this.setValue( (element.getName() == 'pre') ? 'block' : 'inline' );
            },
            commit: function( element ) {
              // Do nothing as this one determines the node type
            }
          },
          {
            type: 'textarea',
            id: 'code',
            default: '',
            rows: 10,
            cols: 50,
            inputStyle: 'height: initial',
            setup: function( element ) {
              this.setValue( element.getText() );
            },
            commit: function( element ) {
              element.setText( this.getValue() );
            }
          }
        ]
      }
    ],
    onShow: function() {
      var selection = editor.getSelection();
      var element = selection.getStartElement();

      if ( element ) {
        if ( element.hasAscendant('code', true) ) {
          element = element.getAscendant( 'code', true );
        } else {
          element = element.getAscendant( 'pre', true );
        }
      }

      if ( !element || ( element.getName() != 'pre' && element.getName() != 'code' ) ) {
        element = editor.document.createElement( 'pre' );
        element.setAttribute( 'class', 'EnlighterJSRAW' );
        this.insertMode = true;
      } else {
        this.insertMode = false;
      }

      this.element = element;

      if ( !this.insertMode ) {
        this.setupContent( element );
      }
    },
    onOk: function() {
      var dialog = this,
        element = this.element,
        mode = dialog.getValueOf( 'tab-general', 'mode' );

      dialog.commitContent( element );

      if ( dialog.insertMode ) {
        if (mode === 'inline') {
          editor.insertHtml( element.getOuterHtml().replace( /^<pre/gi, '<code' ).replace( /pre>$/gi, 'code>' ) );
        } else {
          editor.insertElement(element);
        }
      } else {
        // Change element type if required (replace with the new element that has correct type)
        if ( ( element.getName() == 'pre' && mode == 'inline' ) || ( element.getName() == 'code' && mode == 'block' ) ) {
          var _element = editor.document.createElement( ( mode === 'block' ) ? 'pre' : 'code' );
          _element.setAttribute( 'class', element.getAttribute( 'class' ) );
          _element.data( 'enlighter-language', element.data( 'enlighter-language') );
          _element.setText( element.getText() );
          _element.replace(element);
        }
      }
    }
  };
});
