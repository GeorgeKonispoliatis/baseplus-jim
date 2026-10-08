(function (Drupal, $, drupalSettings, once) {
  'use strict';
  Drupal.behaviors.paragraphs_table_search_reference = {
    attach: function (context) {
      $(once('search-reference', '.paragraphs-table-search', context)).on("click", function () {
        let data = $(this).data();
        let settings = drupalSettings.paragraphs_field_reference[data['field_reference']];
        let safeUrl = Drupal.checkPlain(data['field_reference_url']);
        let table = $(`<table
          class="table table-striped responsive-enabled caption-top"
          id="search-reference"
          data-toggle="table"
          data-search="true"
          data-click-to-select="true"
          data-pagination="true"
          data-id-field="id"
          data-cookie="true"
          data-filter-control="true"
          data-show-search-clear-button="true"
          data-cookie-id-table="saveSearchReference"
          data-url="${safeUrl}">
        <thead class="thead-light">
          <tr></tr>
        </thead>
        </table>`);
        let fields = [];
        settings.columns.forEach(function(column) {
          let th = $('<th>');
          Object.entries(column).forEach(function([key, value]) {
            if(key != 'title') {
              th.data(key, value);
            } else {
              th.html(value);
            }
          });
          let field = column.field;
          if(!['state', 'id', 'name'].includes(field)) {
            fields.push(field);
          }
          table.find('thead tr').append(th);
        });
        let parent = $(this).parent();
        let field_reference = data['field_reference'];
        let dialog = {
          title: data['bsTitle'],
          autoResize: true,
          dialogClass: 'search-dialog',
          width: '90%',
          modal: true,
          height: $(window).height() * 0.8,
          position: {my: "center"},
          buttons: [
            {
              text: "✔ " + Drupal.t('Select'),
              click: function () {
                let $table = $('#search-reference');
                getSelectedPasteClipboard($table.bootstrapTable('getSelections'), parent, field_reference, fields);
                $(this).dialog("close");
              }
            }
          ],
          close: function (event) {
            $(this).dialog('destroy').remove();
          },
          open: function (event, ui) {
            $(event.target).parent().css('background-color','white');
            let $table = $('#search-reference');
            $table.bootstrapTable('destroy').bootstrapTable();
          }
        };
        let showDialog = table.dialog(dialog);
      });

      function getSelectedPasteClipboard(selected, selector, column, fields) {
        let clipboard = '';
        selected.forEach(item => {
          let name = item.name + ` (${item.id})`;
          let parts = [name];
          fields.forEach(field => {
            parts.push(item[field]);
          });
          clipboard += parts.join("\t") + "\n";
        });
        navigator.permissions.query({name: 'clipboard-write'}).then(permissionStatus => {
          if (permissionStatus.state === 'granted' || permissionStatus.state === 'prompt') {
            navigator.clipboard.writeText(clipboard).then(() => {
              const pasteElement = selector.find('.paste-paragraphs-clipboard');
              if (pasteElement.length) {
                pasteElement.data('field_reference', column).trigger('click');
              }
            }).catch(err => {
              console.error('Error copy to clipboard:', err);
            });
          }
        });
      }
    }
  }
}(Drupal, jQuery, drupalSettings, once));
