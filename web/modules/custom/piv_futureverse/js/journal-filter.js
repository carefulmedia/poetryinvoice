(function (Drupal, once) {
  'use strict';

  /**
   * Journal filter behavior to show/hide month options based on year selection.
   */
  Drupal.behaviors.journalFilter = {
    attach: function (context, settings) {
      const yearSelect = context.querySelector('#journal-year-select');
      const monthSelect = context.querySelector('#journal-month-select');
      
      if (!yearSelect || !monthSelect) {
        return;
      }

      once('journal-filter', yearSelect, context).forEach(function (element) {
        // Get the year-to-month mapping from the data attribute
        const mappingData = element.getAttribute('data-year-month-mapping');
        let yearMonthMapping = {};
        
        try {
          yearMonthMapping = JSON.parse(mappingData || '{}');
        } catch (e) {
          console.error('Error parsing year-month mapping:', e);
          return;
        }

        // Store original options for restoration
        const originalOptions = Array.from(monthSelect.options).map(option => ({
          value: option.value,
          text: option.textContent,
          selected: option.selected
        }));

        function filterMonthOptions() {
          const selectedYear = yearSelect.value;
          
          // Clear current options except the first "All Months" option
          while (monthSelect.options.length > 1) {
            monthSelect.removeChild(monthSelect.options[1]);
          }

          // If no year is selected, show all months
          if (!selectedYear || selectedYear === '') {
            originalOptions.slice(1).forEach(optionData => {
              const option = new Option(optionData.text, optionData.value);
              option.selected = optionData.selected;
              monthSelect.appendChild(option);
            });
            return;
          }

          // Get months for the selected year
          const allowedMonths = yearMonthMapping[selectedYear] || [];
          
          // Add only the months that belong to the selected year
          originalOptions.slice(1).forEach(optionData => {
            if (allowedMonths.includes(optionData.value)) {
              const option = new Option(optionData.text, optionData.value);
              option.selected = optionData.selected;
              monthSelect.appendChild(option);
            }
          });

          // If the currently selected month is not in the allowed list, reset selection
          if (monthSelect.value && !allowedMonths.includes(monthSelect.value)) {
            monthSelect.value = '';
          }
        }

        // Filter on year selection change
        yearSelect.addEventListener('change', filterMonthOptions);
        
        // Apply initial filter if a year is already selected
        filterMonthOptions();
      });
    }
  };

})(Drupal, once);