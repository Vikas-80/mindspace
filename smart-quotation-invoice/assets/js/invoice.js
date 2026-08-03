/**
 * Smart Quotation & Invoice Manager - Invoice JavaScript
 * 
 * @package SmartQuotationInvoice
 * @since 1.0.0
 */

(function($) {
    'use strict';

    var SQIInvoice = {
        
        /**
         * Initialize invoice form
         */
        init: function() {
            this.bindEvents();
            this.initAutocomplete();
            this.calculateTotals();
        },
        
        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add row button
            $(document).on('click', '.sqi-add-row-btn', this.addRow);
            
            // Remove row button
            $(document).on('click', '.remove-row-btn', this.removeRow);
            
            // Calculate on input change
            $(document).on('input change', '.sqi-items-table input, .sqi-items-table select', this.calculateRowTotal);
            
            // Save invoice
            $(document).on('click', '.sqi-save-invoice', this.saveInvoice);
            
            // Save as draft
            $(document).on('click', '.sqi-save-draft', this.saveAsDraft);
            
            // Convert to invoice (from quotation)
            $(document).on('click', '.sqi-convert-to-invoice', this.convertToInvoice);
            
            // Duplicate invoice
            $(document).on('click', '.sqi-duplicate-invoice', this.duplicateInvoice);
            
            // Delete invoice
            $(document).on('click', '.sqi-delete-invoice', this.deleteInvoice);
            
            // Generate PDF
            $(document).on('click', '.sqi-generate-pdf', this.generatePDF);
            
            // Send email
            $(document).on('click', '.sqi-send-email', this.sendEmail);
            
            // Print invoice
            $(document).on('click', '.sqi-print-invoice', this.printInvoice);
            
            // Record payment
            $(document).on('click', '.sqi-record-payment', this.recordPayment);
            
            // Customer autocomplete select
            $(document).on('autocompleteselect', '#customer_search', this.onCustomerSelect);
            
            // Product autocomplete select
            $(document).on('autocompleteselect', '.product_search', this.onProductSelect);
        },
        
        /**
         * Initialize autocomplete fields
         */
        initAutocomplete: function() {
            // Customer search
            $('#customer_search').autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: sqiInvoice.ajaxUrl,
                        type: 'GET',
                        data: {
                            action: 'sqi_search_customers',
                            term: request.term,
                            nonce: sqiInvoice.nonce
                        },
                        success: function(data) {
                            if (data.success) {
                                response(data.data.customers);
                            }
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    $('#customer_id').val(ui.item.id);
                    $('#customer_name').val(ui.item.label);
                }
            });
            
            // Product search in rows
            $('.product_search').autocomplete({
                source: function(request, response) {
                    $.ajax({
                        url: sqiInvoice.ajaxUrl,
                        type: 'GET',
                        data: {
                            action: 'sqi_search_products',
                            term: request.term,
                            nonce: sqiInvoice.nonce
                        },
                        success: function(data) {
                            if (data.success) {
                                response(data.data.products);
                            }
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    var row = $(this).closest('tr');
                    row.find('.product_id').val(ui.item.id);
                    row.find('.product_name').val(ui.item.label);
                    row.find('.description').val(ui.item.description || '');
                    row.find('.hsn_sac').val(ui.item.hsn_sac || '');
                    row.find('.unit').val(ui.item.unit || 'pcs');
                    row.find('.rate').val(ui.item.price || 0);
                    row.find('.tax_rate').val(ui.item.gst || 0);
                    SQIInvoice.calculateRowTotal.call(row.find('.quantity')[0]);
                }
            });
        },
        
        /**
         * Handle customer selection
         */
        onCustomerSelect: function(event, ui) {
            var customerData = ui.item;
            
            // Fill customer details if available
            if (customerData.email) {
                $('#customer_email').val(customerData.email);
            }
            if (customerData.phone) {
                $('#customer_phone').val(customerData.phone);
            }
            if (customerData.billing_address) {
                $('#billing_address').val(customerData.billing_address);
            }
            if (customerData.shipping_address) {
                $('#shipping_address').val(customerData.shipping_address);
            }
            if (customerData.gst_number) {
                $('#customer_gst').val(customerData.gst_number);
            }
        },
        
        /**
         * Handle product selection
         */
        onProductSelect: function(event, ui) {
            var row = $(this).closest('tr');
            row.find('.product_id').val(ui.item.id);
            row.find('.product_name').val(ui.item.label);
            row.find('.description').val(ui.item.description || '');
            row.find('.hsn_sac').val(ui.item.hsn_sac || '');
            row.find('.unit').val(ui.item.unit || 'pcs');
            row.find('.rate').val(ui.item.price || 0);
            row.find('.tax_rate').val(ui.item.gst || 0);
            SQIInvoice.calculateRowTotal.call(row.find('.quantity')[0]);
        },
        
        /**
         * Add new row to items table
         */
        addRow: function(e) {
            e.preventDefault();
            
            var rowCount = $('.sqi-items-table tbody tr').length + 1;
            var newRow = `
                <tr>
                    <td>${rowCount}</td>
                    <td>
                        <input type="hidden" name="items[${rowCount}][product_id]" class="product_id" value="">
                        <input type="text" name="items[${rowCount}][product_name]" class="product_search" placeholder="Search product..." style="width: 100%;">
                        <textarea name="items[${rowCount}][description]" class="description" placeholder="Description" rows="2"></textarea>
                    </td>
                    <td><input type="text" name="items[${rowCount}][hsn_sac]" class="hsn_sac" value=""></td>
                    <td><input type="text" name="items[${rowCount}][unit]" class="unit" value="pcs" style="width: 60px;"></td>
                    <td><input type="number" name="items[${rowCount}][quantity]" class="quantity" value="1" min="0" step="0.01" style="width: 70px;"></td>
                    <td><input type="number" name="items[${rowCount}][rate]" class="rate" value="0" min="0" step="0.01" style="width: 80px;"></td>
                    <td>
                        <select name="items[${rowCount}][discount_type]" class="discount_type" style="width: 70px;">
                            <option value="fixed">Fixed</option>
                            <option value="percent">%</option>
                        </select>
                        <input type="number" name="items[${rowCount}][discount_value]" class="discount_value" value="0" min="0" step="0.01" style="width: 70px;">
                    </td>
                    <td><input type="number" name="items[${rowCount}][tax_rate]" class="tax_rate" value="0" min="0" step="0.01" style="width: 60px;"></td>
                    <td class="text-right"><span class="row-total">0.00</span></td>
                    <td class="text-center"><input type="hidden" name="items[${rowCount}][total]" class="item_total" value="0"><button type="button" class="remove-row-btn">&times;</button></td>
                </tr>
            `;
            
            $('.sqi-items-table tbody').append(newRow);
            SQIInvoice.initAutocomplete();
            SQIInvoice.updateRowNumbers();
        },
        
        /**
         * Remove row from items table
         */
        removeRow: function(e) {
            e.preventDefault();
            
            if (confirm(sqiInvoice.strings.confirmDeleteRow || 'Are you sure you want to remove this row?')) {
                $(this).closest('tr').remove();
                SQIInvoice.updateRowNumbers();
                SQIInvoice.calculateTotals();
            }
        },
        
        /**
         * Update row numbers after deletion
         */
        updateRowNumbers: function() {
            $('.sqi-items-table tbody tr').each(function(index) {
                $(this).find('td:first').text(index + 1);
            });
        },
        
        /**
         * Calculate row total
         */
        calculateRowTotal: function() {
            var row = $(this).closest('tr');
            var quantity = parseFloat(row.find('.quantity').val()) || 0;
            var rate = parseFloat(row.find('.rate').val()) || 0;
            var discountType = row.find('.discount_type').val();
            var discountValue = parseFloat(row.find('.discount_value').val()) || 0;
            var taxRate = parseFloat(row.find('.tax_rate').val()) || 0;
            
            // Calculate subtotal
            var subtotal = quantity * rate;
            
            // Apply discount
            var discount = 0;
            if (discountType === 'percent') {
                discount = subtotal * (discountValue / 100);
            } else {
                discount = discountValue;
            }
            
            // Calculate tax
            var taxableAmount = subtotal - discount;
            var taxAmount = taxableAmount * (taxRate / 100);
            
            // Final total
            var total = taxableAmount + taxAmount;
            
            // Update display
            row.find('.row-total').text(total.toFixed(2));
            row.find('.item_total').val(total.toFixed(2));
            
            // Store tax amount
            row.find('.tax_amount').val(taxAmount.toFixed(2));
            
            // Calculate grand totals
            SQIInvoice.calculateTotals();
        },
        
        /**
         * Calculate invoice totals
         */
        calculateTotals: function() {
            var subtotal = 0;
            var totalTax = 0;
            var totalDiscount = 0;
            
            $('.sqi-items-table tbody tr').each(function() {
                var quantity = parseFloat($(this).find('.quantity').val()) || 0;
                var rate = parseFloat($(this).find('.rate').val()) || 0;
                var itemTotal = quantity * rate;
                
                subtotal += itemTotal;
                
                var discountType = $(this).find('.discount_type').val();
                var discountValue = parseFloat($(this).find('.discount_value').val()) || 0;
                
                if (discountType === 'percent') {
                    totalDiscount += itemTotal * (discountValue / 100);
                } else {
                    totalDiscount += discountValue;
                }
                
                totalTax += parseFloat($(this).find('.tax_amount').val()) || 0;
            });
            
            // Invoice level discount
            var invoiceDiscountType = $('#discount_type').val();
            var invoiceDiscountValue = parseFloat($('#discount_value').val()) || 0;
            
            if (invoiceDiscountType === 'percent') {
                totalDiscount += (subtotal - totalDiscount) * (invoiceDiscountValue / 100);
            } else {
                totalDiscount += invoiceDiscountValue;
            }
            
            // Round off
            var roundOff = parseFloat($('#round_off').val()) || 0;
            
            // Grand total
            var grandTotal = subtotal + totalTax - totalDiscount + roundOff;
            
            // Update display
            $('#subtotal').text(subtotal.toFixed(2));
            $('#total_tax').text(totalTax.toFixed(2));
            $('#total_discount').text(totalDiscount.toFixed(2));
            $('#round_off_display').text(roundOff.toFixed(2));
            $('#grand_total').text(grandTotal.toFixed(2));
            
            // Hidden fields for submission
            $('#subtotal_hidden').val(subtotal.toFixed(2));
            $('#total_tax_hidden').val(totalTax.toFixed(2));
            $('#total_discount_hidden').val(totalDiscount.toFixed(2));
            $('#grand_total_hidden').val(grandTotal.toFixed(2));
        },
        
        /**
         * Save invoice
         */
        saveInvoice: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="sqi-spinner"></span> ' + sqiInvoice.strings.saving);
            
            var formData = $('#invoice_form').serialize();
            
            $.ajax({
                url: sqiInvoice.ajaxUrl,
                type: 'POST',
                data: formData + '&action=sqi_save_invoice&status=pending',
                success: function(response) {
                    if (response.success) {
                        alert(sqiInvoice.strings.saved);
                        if (response.data.invoice_id) {
                            window.location.href = '?page=sqi-invoices&invoice_id=' + response.data.invoice_id;
                        }
                    } else {
                        alert(response.data.message || sqiInvoice.strings.error);
                    }
                },
                error: function() {
                    alert(sqiInvoice.strings.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Invoice');
                }
            });
        },
        
        /**
         * Save as draft
         */
        saveAsDraft: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            $btn.prop('disabled', true).html('<span class="sqi-spinner"></span> Saving...');
            
            var formData = $('#invoice_form').serialize();
            
            $.ajax({
                url: sqiInvoice.ajaxUrl,
                type: 'POST',
                data: formData + '&action=sqi_save_invoice&status=draft',
                success: function(response) {
                    if (response.success) {
                        alert('Draft saved successfully!');
                        if (response.data.invoice_id) {
                            window.location.href = '?page=sqi-invoices&invoice_id=' + response.data.invoice_id;
                        }
                    } else {
                        alert(response.data.message || 'Error saving draft.');
                    }
                },
                error: function() {
                    alert('Error saving draft.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Save Draft');
                }
            });
        },
        
        /**
         * Convert quotation to invoice
         */
        convertToInvoice: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to convert this quotation to an invoice?')) {
                return;
            }
            
            var quoteId = $('#quote_id').val();
            
            $.ajax({
                url: sqiInvoice.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sqi_convert_quote_to_invoice',
                    quote_id: quoteId,
                    nonce: sqiInvoice.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Quotation converted to invoice successfully!');
                        window.location.href = '?page=sqi-invoices&invoice_id=' + response.data.invoice_id;
                    } else {
                        alert(response.data.message || 'Error converting quotation.');
                    }
                }
            });
        },
        
        /**
         * Duplicate invoice
         */
        duplicateInvoice: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to duplicate this invoice?')) {
                return;
            }
            
            var invoiceId = $('#invoice_id').val();
            
            $.ajax({
                url: sqiInvoice.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sqi_duplicate_invoice',
                    invoice_id: invoiceId,
                    nonce: sqiInvoice.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Invoice duplicated successfully!');
                        window.location.href = '?page=sqi-invoice-new&invoice_id=' + response.data.invoice_id;
                    } else {
                        alert(response.data.message || 'Error duplicating invoice.');
                    }
                }
            });
        },
        
        /**
         * Delete invoice
         */
        deleteInvoice: function(e) {
            e.preventDefault();
            
            if (!confirm(sqiInvoice.strings.confirmDelete || 'Are you sure you want to delete this invoice?')) {
                return;
            }
            
            var invoiceId = $('#invoice_id').val();
            
            $.ajax({
                url: sqiInvoice.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sqi_delete_invoice',
                    invoice_id: invoiceId,
                    nonce: sqiInvoice.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Invoice deleted successfully!');
                        window.location.href = '?page=sqi-invoices';
                    } else {
                        alert(response.data.message || 'Error deleting invoice.');
                    }
                }
            });
        },
        
        /**
         * Generate PDF
         */
        generatePDF: function(e) {
            e.preventDefault();
            
            var type = $(this).data('type') || 'invoice';
            var id = $('#invoice_id').val() || $('#quote_id').val();
            
            $.ajax({
                url: sqiInvoice.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sqi_generate_pdf',
                    type: type,
                    id: id,
                    nonce: sqiInvoice.nonce
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.download_url;
                    } else {
                        alert(response.data.message || 'Error generating PDF.');
                    }
                }
            });
        },
        
        /**
         * Send email
         */
        sendEmail: function(e) {
            e.preventDefault();
            
            // Open email modal or redirect to email page
            var type = $(this).data('type') || 'invoice';
            var id = $('#invoice_id').val() || $('#quote_id').val();
            
            window.location.href = '?page=sqi-send-email&type=' + type + '&id=' + id;
        },
        
        /**
         * Print invoice
         */
        printInvoice: function(e) {
            e.preventDefault();
            window.print();
        },
        
        /**
         * Record payment
         */
        recordPayment: function(e) {
            e.preventDefault();
            
            var invoiceId = $('#invoice_id').val();
            var amount = $('#payment_amount').val();
            var paymentMethod = $('#payment_method').val();
            var transactionId = $('#transaction_id').val();
            var paymentDate = $('#payment_date').val();
            var notes = $('#payment_notes').val();
            
            if (!amount || amount <= 0) {
                alert('Please enter a valid payment amount.');
                return;
            }
            
            $.ajax({
                url: sqiInvoice.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'sqi_record_payment',
                    invoice_id: invoiceId,
                    amount: amount,
                    payment_method: paymentMethod,
                    transaction_id: transactionId,
                    payment_date: paymentDate,
                    notes: notes,
                    nonce: sqiInvoice.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Payment recorded successfully!');
                        location.reload();
                    } else {
                        alert(response.data.message || 'Error recording payment.');
                    }
                }
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        SQIInvoice.init();
    });
    
})(jQuery);
