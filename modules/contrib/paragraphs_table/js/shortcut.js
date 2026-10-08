(function ($, Drupal, once) {
  Drupal.behaviors.shortcutParagraphsTable = {
    attach: function (context, settings) {
      $(once('field--widget-paragraphs-table-widget', '.field--widget-paragraphs-table-widget table', context)).each(function () {
        const table = $(this);
        // Automatically click add more button when focused
        table.closest('.form-item').find("input[name$='add_more']").on('focus', function () {
          $(this).click();
        });

        // Function to duplicate current row
        function duplicateRow(row) {
          $(row).find("button[name$='duplicate']").click();
        }

        // Function to add a new row
        function addNewRow() {
          table.closest('.form-item').find("input[name$='add_more']").click();
        }

        // Function to move row up
        function moveRowUp(row) {
          const prevRow = row.prev();
          if (prevRow.length) {
            row.insertBefore(prevRow);
          }
        }

        // Function to move row down
        function moveRowDown(row) {
          const nextRow = row.next();
          if (nextRow.length) {
            row.insertAfter(nextRow);
          }
        }

        // Function to focus cell above
        function focusCellAbove(cell) {
          const colIndex = cell.index();
          const prevRow = cell.closest("tr").prev();
          if (prevRow.length) {
            prevRow.find("td").eq(colIndex).find("input, select, textarea").focus();
          }
        }

        // Function to focus cell below
        function focusCellBelow(cell) {
          const colIndex = cell.index();
          const nextRow = cell.closest("tr").next();
          if (nextRow.length) {
            nextRow.find("td").eq(colIndex).find("input, select, textarea").focus();
          }
        }

        // Function to focus cell to the left
        function focusCellLeft(cell) {
          const prevCell = cell.prev();
          if (prevCell.length) {
            prevCell.find("input, select, textarea").focus();
          }
        }

        // Function to focus cell to the right
        function focusCellRight(cell) {
          const nextCell = cell.next();
          if (nextCell.length) {
            nextCell.find("input, select, textarea").focus();
          }
        }

        // Event listener for keyboard shortcuts
        table.on("keydown", function (e) {
          const focusedElement = $(":focus");
          const currentCell = focusedElement.closest("td");
          const currentRow = focusedElement.closest("tr");

          if (!currentRow.length || !table.has(currentRow).length) return;

          switch (true) {
            case e.ctrlKey && e.key === "d":
              e.preventDefault();
              duplicateRow(currentRow);
              break;
            case e.altKey && e.key === "n":
              e.preventDefault();
              addNewRow();
              break;
            case e.ctrlKey && e.key === "ArrowUp":
              e.preventDefault();
              moveRowUp(currentRow);
              break;
            case e.ctrlKey && e.key === "ArrowDown":
              e.preventDefault();
              moveRowDown(currentRow);
              break;
            case e.key === "ArrowUp":
              e.preventDefault();
              focusCellAbove(currentCell);
              break;
            case e.key === "ArrowDown":
              e.preventDefault();
              focusCellBelow(currentCell);
              break;
            case e.key === "ArrowLeft":
              e.preventDefault();
              focusCellLeft(currentCell);
              break;
            case e.key === "ArrowRight":
              e.preventDefault();
              focusCellRight(currentCell);
              break;
          }
        });
      });
    },
  };
})(jQuery, Drupal, once);
