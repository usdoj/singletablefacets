/**
 * @file
 * Javascript enhancements for SingleTableFacets.
 */
$(document).ready(function () {

    // Checkboxes.
    $('.stf-facet-checkboxes a, .stf-facet-radios a').not('.stf-facet-processed').each(function (index) {
        // To avoid confusion with "this", a variable referring to the link.
        var link = this;
        // Add the class now so that it is not processed twice.
        $(link).addClass('stf-facet-processed');
        // Unique id and text for checkbox/label.
        var id = 'stf-facet-cb-' + index;
        var labelText = $(link).text();
        // Decide whether the checkbox should be checked.
        var checked = '';
        if ($(link).hasClass('stf-facet-item-active')) {
            checked = ' checked="checked"';
        }
	var inputType = 'checkbox';
	if ($(link).parents('.stf-facet-radios').length) {
	    inputType = 'radio';
	}
        // Create the DOM elements for the checkbox and label.
        var $checkbox = $('<input type="' + inputType + '" id="' + id + '"' + checked + ' />');
        var $label = $('<label for="' + id + '">' + labelText + '</label>');
        // Insert them before the link.
        $checkbox.insertBefore(link);
        $label.insertBefore(link);
        // Hide the link.
        $(link).hide();
        // Give the checkbox/label click behavior, similar to the link.
        $checkbox.change(function () {
            window.location = $(link).attr('href');
        });
    });

    // Help text collapsing.
    $('.stf-facet-collapse').not('.stf-facet-collapse-processed').each(function (index) {
        // To avoid confusion with "this", a variable referring to the item.
        var collapsible = this;
        // Add the class now so that it is not processed twice.
        $(collapsible).addClass('stf-facet-collapse-processed');

        var trigger = $(collapsible).find('.stf-facet-collapse-trigger');
        var contents = $(collapsible).find('.stf-facet-collapse-inner');

        // Hide the contents.
        $(contents).hide();
        // Add the behavior.
        $(trigger).click(function () {
            $(contents).slideToggle();
            $(this).toggleClass('stf-facet-collapse-active');
        });
    });

    // Facet item collapsing.
    $('.stf-facet-collapse-outer').not('.stf-facet-collapse-processed').each(function (index) {
        // To avoid confusion with "this", a variable referring to the item.
        var collapsible = this;
        // Add the class now so that it is not processed twice.
        $(collapsible).addClass('stf-facet-collapse-processed');

        // First hide all the collapsed items.
        $(collapsible).find('.stf-facet-item-collapsed').hide();

        var labelOn = 'Show more';
        var labelOff = 'Show fewer';
        if ($(collapsible).hasClass('stf-facet-collapse-all')) {
            labelOn = 'Show filters';
            labelOff = 'Hide filters';
        }
        var trigger = $('<div>' + labelOn + '</div>')
            .addClass('stf-facet-collapse-trigger')
            .click(function () {
                $(this).parent().find('.stf-facet-item-collapsed').slideToggle();
                if ($(this).text() == labelOn) {
                    $(this).text(labelOff);
                }
                else {
                    $(this).text(labelOn);
                }
                $(this).toggleClass('stf-facet-collapse-active');
            });
        $(collapsible).append(trigger);
    });
});
