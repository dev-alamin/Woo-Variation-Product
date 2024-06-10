jQuery(document).ready(function($) {
var previousVariationValue = '';
var selectedFlavour = $('.selected_flavour');

if (selectedFlavour.is(':empty')) {
    selectedFlavour.text('Choose a flavour');
}

function updateURL(attributeId) {
    var selectElement = $('#' + attributeId);

    selectElement.on('change', function() {
        // Get the variation value
        var variationValue = $(this).find('option:selected').text().trim();

        $('.product_title span').text(variationValue);
        // Make the variation value URL-friendly
        variationValue = variationValue.toLowerCase()
            .replace(/\s+/g, '-')           // Replace spaces with hyphens
            .replace(/[(){}\[\]&]/g, '')    // Remove parentheses, brackets, and ampersand
            .replace(/-+/g, '-');           // Replace multiple hyphens with a single hyphen

        var currentURL = window.location.href;
        var hasTrailingSlash = currentURL.endsWith('/');
        var urlParts = currentURL.split('/');
        var lastPartIndex = urlParts.length - 1;

        // Remove empty parts at the end of the array
        while (lastPartIndex >= 0 && urlParts[lastPartIndex] === '') {
            urlParts.splice(lastPartIndex, 1);
            lastPartIndex--;
        }

        // Update the last part of the URL or append the new value
        if (urlParts.length >= 5) {
            urlParts[urlParts.length - 1] = variationValue;
        } else {
            urlParts.push(variationValue);
        }

        var updatedURL = urlParts.join('/') + (hasTrailingSlash ? '/' : '');
        history.pushState({}, '', updatedURL);
    });
}

updateURL('pa_flavour');
updateURL('pa_product-colour');

});
