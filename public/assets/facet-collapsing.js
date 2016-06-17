$(document).ready(function() {

  // First the help text.
  $('.doj-facet-collapse').not('.facet-collapse-processed').each(function(index) {
    // To avoid confusion with "this", a variable referring to the item.
    var collapsible = this;
    // Add the class now so that it is not processed twice.
    $(collapsible).addClass('facet-collapse-processed');

    var trigger = $(collapsible).find('.doj-facet-collapse-trigger');
    var contents = $(collapsible).find('.doj-facet-collapse-inner');

    // Hide the contents.
    $(contents).hide();
    // Add the behavior.
    $(trigger).click(function() {
      $(contents).slideToggle();
      $(this).toggleClass('doj-facet-collapse-active');
    });
  });

  // Now the facet items themselves, which need to be handled differently.
  $('.doj-facet-collapse-outer').not('.facet-collapse-processed').each(function(index) {
    // To avoid confusion with "this", a variable referring to the item.
    var collapsible = this;
    // Add the class now so that it is not processed twice.
    $(collapsible).addClass('facet-collapse-processed');

    // First hide all the collapsed items.
    $(collapsible).find('.doj-facet-item-collapsed').hide();

    var labelOn = 'Show more';
    var labelOff = 'Show fewer';
    if ($(collapsible).hasClass('doj-facet-collapse-all')) {
      labelOn = 'Show filters';
      labelOff = 'Hide filters';
    }
    var trigger = $('<div>' + labelOn + '</div>')
      .addClass('doj-facet-collapse-trigger')
      .click(function() {
        $(this).parent().find('.doj-facet-item-collapsed').slideToggle();
        if ($(this).text() == labelOn) {
          $(this).text(labelOff);
        }
        else {
          $(this).text(labelOn);
        }
        $(this).toggleClass('doj-facet-collapse-active');
      });
    $(collapsible).append(trigger);
  });
});
