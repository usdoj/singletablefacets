$(document).ready(function() {
  $('.facet-items a').not('.facet-processed').each(function(index) {
    // To avoid confusion with "this", a variable referring to the link.
    var link = this;
    // Add the class now so that it is not processed twice.
    $(link).addClass('facet-processed');
    // Unique id and text for checkbox/label.
    var id = 'doj-facet-cb-' + index;
    var labelText = $(link).text();
    // Decide whether the checkbox should be checked.
    var checked = '';
    if ($(link).hasClass('doj-facet-item-active')) {
      checked = ' checked="checked"';
    }
    // Create the DOM elements for the checkbox and label.
    var $checkbox = $('<input type="checkbox" id="' + id + '"' + checked + ' />');
    var $label = $('<label for="' + id + '">' + labelText + '</label>');
    // Insert them before the link.
    $checkbox.insertBefore(link);
    $label.insertBefore(link);
    // Hide the link.
    $(link).hide();
    // Give the checkbox/label click behavior, similar to the link.
    $checkbox.change(function() {
      window.location = $(link).attr('href');
    });
  });
});
