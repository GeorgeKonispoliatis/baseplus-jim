(function (Drupal, $, once) {
  Drupal.behaviors.pasteParagraphsClipboard = {
    attach: function attach(context, settings) {
      $(once('paste-clipboard', '.paste-paragraphs-clipboard', context)).on("click", function () {
        const id_table = $(this).data("table");
        const tab = "\t";
        const breakLine = "\n";
        navigator.permissions.query({ name: 'clipboard-read' }).then(permissionStatus => {
          if (permissionStatus.state === 'granted' || permissionStatus.state === 'prompt') {
          navigator.clipboard.readText().then(text => {
            let items = $.trim(text).split(breakLine);
            let data = [];
            $.each(items, function (key, val) {
              data[key] = $.trim(val).split(tab);
            });
            let dataCount = data.length;
            let rowCount = $('table#' + id_table + ' > tbody').find("tr").length;
            if (rowCount < dataCount) {
              let rowsToAdd = dataCount - rowCount;
              let firstRow = $('table#' + id_table).find("tbody tr:first");
              for (let i = 0; i < rowsToAdd; i++) {
                let newRow = firstRow.clone();
                newRow.find("input, select, textarea").each(function () {
                  let name = $(this).attr("name");
                  let id = $(this).attr("id");
                  if (name) {
                    $(this).attr("name", name.replace("[0][subform", "[" + (i + rowCount) + "][subform"));
                  }
                  if (id) {
                    $(this).attr("id", id.replace("-0-subform", "-" + (i + rowCount) + "-subform"));
                  }
                  if ($(this).is("input") || $(this).is("textarea")) {
                    $(this).val("");
                  }
                });
                $('table#' + id_table + ' > tbody').append(newRow); // fill them with first row
              }
            }
            $('table#' + id_table + ' > tbody  > tr').each(function (row, tr) {
              if(row in data){
                let removeFirstCol = 0;
                if($(this).hasClass('draggable')){
                  removeFirstCol = 1;
                }
                $(this).find('td').each(function (col, td) {
                  col -= removeFirstCol;
                  if(col in data[row]){
                    $(this).find("input").val(data[row][col]);
                    //if select check have value
                    const exists = 0 != $(this).find('select').length;
                    if(exists){
                      let that = $(this);
                      $.each($(this).find("select").prop("options"), function (i, opt) {
                        if(opt.textContent == data[row][col] || opt.value == data[row][col]){
                          that.find("select").val(opt.value);
                        }
                      })
                    }
                  }
                });
              }
            });
            let btnAddMore = $('table#' + id_table).closest('.field--widget-paragraphs-table-widget').find(".paragraphs-add-wrapper input[type='submit']");
            btnAddMore.off('click').click();
          })
          .catch(() => {
            console.log('Failed to read from clipboard.');
          });
          }
        });
      });

      // Paste form excel to first rows.
      $('.field--widget-paragraphs-table-widget table tbody input:first').on('paste', function (e) {
        var $this = $(this);
        $.each(e.originalEvent.clipboardData.items, function (i, v) {
          if (v.type === 'text/plain') {
            v.getAsString(function (text) {
              var data = [];
              text = text.trim('\r\n');
              text.split('\r\n').forEach((v2, row) => {
                let rows = v2.split('\t');
                data[row] = rows;
              });
              $this.closest('tbody').find('tr').each(function (row, tr) {
                if (row in data) {
                  let removeFirstCol = 0;
                  if ($(this).hasClass('draggable')) {
                    removeFirstCol = 1;
                  }
                  $(this).find('td').each(function (col, td) {
                    col -= removeFirstCol;
                    if (col in data[row]) {
                      $(this).find("input").val(data[row][col]);
                      //if select check have value
                      const exists = 0 != $(this).find('select').length;
                      if (exists) {
                        let that = $(this);
                        $.each($(this).find("select").prop("options"), function (i, opt) {
                          if (opt.textContent == data[row][col] || opt.value == data[row][col]) {
                            that.find("select").val(opt.value);
                          }
                        })
                      }
                    }
                  });
                }
              });
            });
          }
        });
        return false;
      });

    }
  };
}(Drupal, jQuery, once));
