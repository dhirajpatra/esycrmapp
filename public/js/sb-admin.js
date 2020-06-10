(function ($) {
    "use strict"; // Start of use strict

    // multiple data-toggle solutions where need more than one data-toggle values
    $("[data-hover='tooltip']").tooltip();

    // $(".close").on('click', function() {
    //     $('.close').modal('hide');
    //     return false;
    // });

    // data tables ready
    $(document).ready(function () {
        if ($('.datatable').length === 1) {
            $('.datatable').DataTable({
                "bAutoWidth": true,
                "bPaginate": true,
                "bLengthChange": true,
                "bInfo": false,
            });
        }
    });

    // Toggle the side navigation
    // sidebar menu toggle hide and show by default sidebar will be in smaller width
    // var sidebar = $('.sidebar');
    // sidebar.toggleClass("toggled");
    $("#sidebarToggle").on('click', function (e) {
        e.preventDefault();
        $("body").toggleClass("sidebar-toggled");
        $(".sidebar").toggleClass("toggled");
    });

    // Prevent the content wrapper from scrolling when the fixed side navigation hovered over
    $('body.fixed-nav .sidebar').on('mousewheel DOMMouseScroll wheel', function (e) {
        if ($(window).width() > 768) {
            var e0 = e.originalEvent,
                delta = e0.wheelDelta || -e0.detail;
            this.scrollTop += (delta < 0 ? 1 : -1) * 30;
            e.preventDefault();
        }
    });

    // Scroll to top button appear
    $(document).on('scroll', function () {
        var scrollDistance = $(this).scrollTop();
        if (scrollDistance > 100) {
            $('.scroll-to-top').fadeIn();
        } else {
            $('.scroll-to-top').fadeOut();
        }
    });

    // Smooth scrolling using jQuery easing
    $(document).on('click', 'a.scroll-to-top', function (event) {
        var $anchor = $(this);
        $('html, body').stop().animate({
            scrollTop: ($($anchor.attr('href')).offset().top)
        }, 1000, 'easeInOutExpo');
        event.preventDefault();
    });

    // registration validation check
    $('.validatedForm').validate({
        rules: {
            inputPassword: {
                minlength: 5
            },
            confirmPassword: {
                minlength: 5,
                equalTo: "#inputPassword"
            }
        }
    });

    $('#registerButton').click(function () {
        console.log($('.validatedForm').valid());
        // $('#login_result').toggleClass('alert-danger alert-success');
        // $('#login_result').html('<small>Now click to Register button</small>')
        // $("#registerButton").attr("disabled", true); // submit btn disabled
        // $("#registerButton").attr("disabled", false); // submit btn enabled
    });

    $('#btn_activation_submit').click(function () {
        console.log($('.validatedForm').valid());
    });

    $('#btn_change_password_submit').click(function () {
        console.log($('.validatedForm').valid());
        $("#registerButton").attr("disabled", true);
    });

    // add form tabs related function
    $('#tabs_add_forms li a').click(function () {

        var t = $(this).attr('id');

        if ($(this).hasClass('inactive')) { //this is the start of our condition
            $('#tabs_add_forms li a').addClass('inactive');

            $(this).removeClass('inactive');

            $('.container_tabs_add_forms').hide();

            $('#' + t + 'C').fadeIn('slow');

        }
    });


    // add form tabs related function to sow the content of tabs for leads
    $('#tabs_add_leads li a').click(function () {

        // datatable
        $.fn.dataTable.ext.errMode = 'none';

        var t = $(this).attr('id');

        var dInput = $(this).text();
        $.post("/sales/get_deal_details", {
            contact: dInput
        },
            function (data, status) {
                //alert("Data: " + data + "\nStatus: " + status);
                $('#' + t + 'C').html(data);

                $('.datatable_deals').DataTable({
                    "bAutoWidth": true,
                    "bPaginate": true,
                    "bLengthChange": true,
                    "bInfo": false,
                });

            });

        if ($(this).hasClass('inactive')) { //this is the start of our condition
            $('#tabs_add_leads li a').addClass('inactive');
            $('.budget_total_active').removeClass('budget_total_active');
            $('#budget_' + t).toggleClass('budget_total_active');

            $(this).removeClass('inactive');

            $('.container_tabs_add_leads').hide();

            $('#' + t + 'C').fadeIn('slow');
        }

    });

    // easy Ajax call for many lookups
    $("#contact_lookup").keyup(function () {
        var dInput = this.value;

        $.post("/sales/contact_lookup", {
            contact: dInput
        },
            function (data, status) {
                //alert("Data: " + data + "\nStatus: " + status);
                if (data != '') {
                    $('#contact').html(data);
                    $('#contact').show();

                    $('#contact_select').change(function () {
                        var value = $('#contact_select').find(":selected").val().split('#');
                        $('#contact_lookup').val(value[0]);
                        $('#email').val(value[1]);
                        $('#company').val(value[2]);
                        $('#phone').val(value[3]);
                        $('#contact').html();
                        $('#contact').hide();
                    });
                }

            });
    });

    // add tab forms submit functions
    $("#frm_add_deal").submit(function (event) {
        event.preventDefault(); //prevent default action
        $('#deal_submit').attr("disabled", true);
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            $('#deal_submit').attr("disabled", false);
            if (response == '200') {
                $('#server-results').removeClass('alert-danger');
                $('#server-results').addClass('alert-success');
                response = 'Deal successfully saved. You can add To do now.';
                $("#server-results").html(response);
                $("#frm_add_deal").trigger("reset");
                window.setTimeout(function () {
                    location.reload();
                }, 1000);
            } else if (response == '409') {
                response = 'Deal not saved. Form has been tampered. Or your Deal limit reached. Kindly contact admin.';
                $("#server-results").html(response);

            } else {
                response = 'Deal not saved. Kindly check if you have Contact otherwise add Contact first.';
                $("#server-results").html(response);
            }

        });
    });

    // add tab forms submit functions
    $("#frm_add_todo").submit(function (event) {
        event.preventDefault(); //prevent default action
        $('#todo_submit').attr('disabled', true);
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            $('#todo_submit').attr('disabled', false);
            if (response == '200') {
                $('#server-result_todo').removeClass('alert-danger');
                $('#server-result_todo').addClass('alert-success');
                response = 'To do successfully saved.';
                $("#server-result_todo").html(response);
                $("#frm_add_todo").trigger("reset");
                window.setTimeout(function () {
                    location.reload();
                }, 1000);
            } else {
                response = 'Todo not saved. Kindly check if you have Contact and Deal otherwise add Contact and Deal first.';
                $("#server-result_todo").html(response);
            }

        });
    });

    // add tab contact forms submit functions from sales/sidebar.html.twig
    $("#frm_add_contact").submit(function (event) {
        event.preventDefault(); //prevent default action
        $('#contact_submit').attr("disabled", true);
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            $('#contact_submit').attr("disabled", false);
            if (response == '200') {
                $('#server-results_contact').removeClass('alert-danger');
                $('#server-results_contact').addClass('alert-success');
                response = 'Contact successfully saved.';
                $("#server-results_contact").html(response);
                $("#frm_add_contact").trigger("reset");
                window.setTimeout(function () {
                    location.reload();
                }, 1000);
            } else if (response == '409') {
                // console.log(response);
                response = 'Duplicate contact or your company contacts limit reached kindly contact to admin.';
                $("#server-results_contact").html(response);
            } else {
                response = 'Contact not saved.';
                $("#server-results_contact").html(response);
            }

        });
    });

    // add form tabs related function
    $('#tabs_add_forms li a').click(function () {

        var t = $(this).attr('id');

        if ($(this).hasClass('inactive')) { //this is the start of our condition
            $('#tabs_add_forms li a').addClass('inactive');

            $(this).removeClass('inactive');

            $('.container_tabs_add_forms').hide();

            $('#' + t + 'C').fadeIn('slow');

        }
    });


    // add form tabs related function to sow the content of tabs for comments
    $('#tabs_add_comments li a').click(function () {

        var t = $(this).attr('id');

        var note_id = $('#note_id').val();

        if (t == 'tabs_add_comments_tab2') {
            $.post("/sales/get_note_comments", {
                note_id: note_id
            },
                function (data, status) {
                    //alert("Data: " + data + "\nStatus: " + status);
                    $('#' + t + 'C').html(data);
                });
        }

        if ($(this).hasClass('inactive')) { //this is the start of our condition
            $('#tabs_add_comments li a').addClass('inactive');

            $(this).removeClass('inactive');

            $('.container_tabs_add_comments').hide();

            $('#' + t + 'C').fadeIn('slow');
        }

    });

    // add tab forms submit functions
    $("#frm_add_comment").submit(function (event) {
        event.preventDefault(); //prevent default action
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //

            if (response == '200') {
                response = 'Comment successfully saved.';
                $("#server-results_comment").html(response);
                $("#frm_add_comment").trigger("reset");
                window.setTimeout(function () {
                    location.reload();
                }, 1000);
            } else {
                response = 'Comment not saved.';
                $("#server-results_comment").html(response);
            }

        });
    });

    // dynamically created comments form to delete need late binding
    $(document).on('submit', '#frm_delete_comment', function (event) {
        event.preventDefault(); //prevent default action
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission
        var hidden_values = form_data.split('&');
        hidden_values = hidden_values[1].split('=');
        var note_id = hidden_values[1];

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            response = $.parseJSON(response);
            if (response.status == '200') {
                note_id = response.data;
                var response_display = 'Comment successfully deleted.';
                $("#server-result_comment_edit").addClass('alert-success');
                $("#server-result_comment_edit").html(response_display);

                setTimeout(
                    function () {
                        // refresh the table
                        $.post("/sales/get_note_comments", {
                            note_id: note_id
                        },
                            function (data, status) {
                                // alert("Data: " + data + "\nStatus: " + status);
                                $('#tabs_add_comments_tab2C').html(data);
                            });
                    }, 1000);


            } else {
                $("#server-result_comment_edit").addClass('alert-danger');
                response = 'Comment not deleted.';
                $("#server-result_comment_edit").html(response);
            }

        });
    });

    // when close down Comment modal need to reset the form to display mode
    // for completed task add comment form display set to none
    $("#add_comment").on("hidden.bs.modal", function () {
        // window.location.reload();
        $("#frm_add_comment").show();
        var response = 'Fill the below form. Red border means required field.';
        $("#server-results_comment").toggleClass('alert-success');
        $("#server-results_comment").html(response);
    });

    // easy Ajax call for many lookups
    $("#edit_deal_contact_lookup").keyup(function () {
        var dInput = this.value;

        $.post("/sales/edit_contact_lookup", {
            contact: dInput
        },
            function (data, status) {
                //alert("Data: " + data + "\nStatus: " + status);
                if (data != '') {
                    $('#edit_deal_contact').html(data);
                    $('#edit_deal_contact').show();

                    $('#edit_deal_contact_select').change(function () {
                        var value = $('#edit_deal_contact_select').find(":selected").val().split('#');
                        $('#edit_deal_contact_lookup').val(value[0]);
                        $('#edit_deal_email').val(value[1]);
                        $('#edit_deal_company').val(value[2]);
                        $('#edit_deal_phone').val(value[3]);
                        $('#edit_deal_contact').html();
                        $('#edit_deal_contact').hide();
                    });
                }

            });
    });

    // edit deal forms submit functions
    $(document).on('submit', '#frm_edit_deal', function (event) {
        event.preventDefault(); //prevent default action
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        // need to check the existing data and submitted are same or not if same then no submit to server
        var data = form_data_serialize_to_assoc_array(form_data);
        var original_data = form_data_serialize_to_assoc_array(data.original_values);
        var id = null;
        var proposal_due_date = null;
        var data_changed = 0;
        // checking whether user changed the data or false submit
        $(original_data).each(function (index, item) {
            // from default / first deals tab
            if (typeof (data.edit_deal_proposal_due_date) == "undefined") {
                proposal_due_date = eval('data.edit_deal_proposal_due_date_' + data.edit_deal_id);
            } else {
                proposal_due_date = data.edit_deal_proposal_due_date;
            }

            // set id value
            id = item.edit_deal_id;

            if (item.edit_deal_amount != data.edit_deal_amount) {
                data_changed = 1;
                return false;
            }
            if (item.edit_deal_company != data.edit_deal_company) {
                data_changed = 1;
                return false;
            }
            if (item.edit_deal_contact_lookup != data.edit_deal_contact_lookup) {
                data_changed = 1;
                return false;
            }
            if (item.edit_deal_deal != data.edit_deal_deal) {
                data_changed = 1;
                return false;
            }
            if (item.edit_deal_deliverables != data.edit_deal_deliverables) {
                data_changed = 1;
                return false;
            }
            if (item.edit_deal_phone != data.edit_deal_phone) {
                data_changed = 1;
                return false;
            }
            if (item.edit_deal_project_description != data.edit_deal_project_description) {
                data_changed = 1;
                return false;
            }
            if (item.edit_deal_proposal_due_date != proposal_due_date) {
                data_changed = 1;
                return false;
            }
            if (item.edit_deal_rating != data.edit_deal_rating) {
                data_changed = 1;
                return false;
            }
            if (item.edit_deal_stage != data.edit_deal_stage) {
                data_changed = 1;
                return false;
            }
            if (item.edit_deal_email != data.edit_deal_email) {
                data_changed = 1;
                return false;
            }
        });

        // need to update
        if (data_changed == 1) {
            $.ajax({
                url: post_url,
                type: request_method,
                data: form_data,
                cache: false,
                processData: false,
            }).done(function (response) { //
                if (response == '200') {
                    response = 'Deal successfully updated.';
                    $(".edit_deal_server-results").html(response);
                    $(".simple-form").trigger("reset");
                    $('.simple-form').hide();
                    window.setTimeout(function () {
                        location.reload();
                    }, 1000);
                } else if (response == '409') {
                    response = 'Deal not updated. All values are same.';
                    $(".edit_deal_server-results").html(response);
                } else {
                    response = 'Deal not updated.';
                    $(".edit_deal_server-results").html(response);
                }
            });
        } else {
            var response = 'Deal not updated, no change found.';
            $(".edit_deal_server-results").html(response);
            $(".edit_deal_server-results_deal-other").html(response);
        }
    });

    // delete deal forms submit functions form created in leads
    // $("#frm_data_deal_delete_other").submit(function(event) {
    //     event.preventDefault(); //prevent default action
    //     var r = confirm("Are you sure to delete this deal?");
    //     if (r != true) {
    //         return false;
    //     }
    //     var post_url = $(this).attr("action"); //get form action url
    //     var request_method = $(this).attr("method"); //get form GET/POST method
    //     var form_data = $(this).serialize(); //Encode form elements for submission

    //     $.ajax({
    //         url: post_url,
    //         type: request_method,
    //         data: form_data
    //     }).done(function(response) { //
    //         if (response == '200') {
    //             response = 'Deal successfully deleted.';
    //             $('#response_display').addClass('alert-success');
    //             $("#response_display").html(response);
    //             $("#frm_data_deal_delete").trigger("reset");
    //             $('#frm_data_deal_delete').hide();
    //         } else if (response == '409') {
    //             $('#response_display').addClass('alert-warning');
    //             response = 'Deal not updated. All values are same.';
    //             $("#response_display").html(response);
    //         } else {
    //             $('#response_display').addClass('alert-danger');
    //             response = 'Deal not updated.';
    //             $("#response_display").html(response);
    //         }
    //     });
    // });

    // other deal tab delete deal forms submit functions form created in leads
    $(document).on('submit', "#frm_data_deal_delete_other", function (event) {
        event.preventDefault(); //prevent default action
        var r = confirm("Are you sure to hide this deal?");
        if (r != true) {
            return false;
        }
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            if (response == '200') {
                response = 'Deal successfully deleted.';
                $('#response_display').addClass('alert-success');
                $("#response_display").html(response);
                $("#frm_data_deal_delete").trigger("reset");
                $('#frm_data_deal_delete').hide();

                // need to reload the page
                window.setTimeout(function () {
                    location.reload();
                }, 1000);

            } else if (response == '409') {
                $('#response_display').addClass('alert-warning');
                response = 'Deal not updated. All values are same.';
                $("#response_display").html(response);
            } else {
                $('#response_display').addClass('alert-danger');
                response = 'Deal not updated.';
                $("#response_display").html(response);
            }
        });
    });

    // other deal tab edit deal forms submit functions form created in get_deal_details with late binding
    // $(document).on('submit', '.deal-other', function(event) {
    //     event.preventDefault(); //prevent default action
    //     var post_url = $(this).attr("action"); //get form action url
    //     var request_method = $(this).attr("method"); //get form GET/POST method
    //     var form_data = $(this).serialize(); //Encode form elements for submission
    //     var data = form_data_serialize_to_assoc_array(form_data);
    //     var original_data = form_data_serialize_to_assoc_array(data.original_values);
    //     var proposal_due_date = null;
    //     var data_changed = 0;
    //     // checking whether user changed the data or false submit
    //     $(original_data).each(function(index, item){
    //         // from default / first deals tab
    //         if( typeof(data.edit_deal_proposal_due_date) == "undefined") {
    //             proposal_due_date = eval('data.edit_deal_proposal_due_date_' + data.edit_deal_id);
    //         } else {
    //             proposal_due_date = data.edit_deal_proposal_due_date;
    //         }

    //         if(item.edit_deal_amount != data.edit_deal_amount) {
    //             data_changed = 1;
    //             return false;
    //         }
    //         if(item.edit_deal_company != data.edit_deal_company) {
    //             data_changed = 1;
    //             return false;
    //         }
    //         if(item.edit_deal_contact_lookup != data.edit_deal_contact_lookup) {
    //             data_changed = 1;
    //             return false;
    //         }
    //         if(item.edit_deal_deal != data.edit_deal_deal) {
    //             data_changed = 1;
    //             return false;
    //         }
    //         if(item.edit_deal_deliverables != data.edit_deal_deliverables) {
    //             data_changed = 1;
    //             return false;
    //         }
    //         if(item.edit_deal_phone != data.edit_deal_phone) {
    //             data_changed = 1;
    //             return false;
    //         }
    //         if(item.edit_deal_project_description != data.edit_deal_project_description) {
    //             data_changed = 1;
    //             return false;
    //         }
    //         if(item.edit_deal_proposal_due_date != proposal_due_date) {
    //             data_changed = 1;
    //             return false;
    //         }
    //         if(item.edit_deal_rating != data.edit_deal_rating) {
    //             data_changed = 1;
    //             return false;
    //         }
    //         if(item.edit_deal_stage != data.edit_deal_stage) {
    //             data_changed = 1;
    //             return false;
    //         }
    //         if(item.edit_deal_email != data.edit_deal_email) {
    //             data_changed = 1;
    //             return false;
    //         }
    //     });

    //     // need to update
    //     if(data_changed == 1) {
    //         $.ajax({
    //             url: post_url,
    //             type: request_method,
    //             data: form_data
    //         }).done(function(response) { //
    //             if (response == '200') {
    //                 response = 'Deal successfully updated.';
    //                 $("#edit_deal_server-results_deal-other").html(response);
    //                 $(".deal-other").trigger("reset");
    //                 $('#edit_deal_form_other').hide();
    //             } else if (response == '409') {
    //                 response = 'Deal not updated. All values are same.';
    //                 $("#edit_deal_server-results_deal-other").html(response);
    //             } else {
    //                 response = 'Deal not updated.';
    //                 $("#edit_deal_server-results_deal-other").html(response);
    //             }
    //         });
    //     } else {
    //         var response = 'Deal not updated, no change found.';
    //         $("#edit_deal_server-results_deal-other").html(response);
    //         $("#edit_deal_server-results").html(response);
    //     }
    // });

    // reload the page when close the edit deal modal
    $(document).on("hidden.bs.modal", function () {
        // window.location.reload();
    });

    // all datepickers of deals
    $('.edit_deal_proposal_due_date').each(function (key, val) {
        $("#" + $(val).attr('id')).datepicker({
            minDate: new Date({ dateFormat: 'dd-mm-yy' })
        });
    });

    $(document).on('click', '#sales_task_edit_submit', function (event) {
        $('.edit_deal_proposal_due_date').each(function (key, val) {
            $("#" + $(val).attr('id')).datepicker({
                minDate: new Date({ dateFormat: 'dd-mm-yy' })
            });
        });
    });
    // deals -> add -> deal -> date picker
    $("#proposal_due_date").datepicker({
        minDate: new Date({ dateFormat: 'dd-mm-yy' })
    });
    $("#todo_due_date").datepicker({
        minDate: new Date({ dateFormat: 'dd-mm-yy' })
    });
    $("#edit_task_modal #todo_due_date_edit").datepicker({
        minDate: new Date({ dateFormat: 'dd-mm-yy' })
    });
    $("#edit_deal_proposal_due_date").datepicker({
        minDate: new Date({ dateFormat: 'dd-mm-yy' })
    });

    // for analytics from and to date
    jQuery("#sales_analytics_from").datepicker({
        dateFormat: 'mm/dd/yy',
        changeMonth: true,
        changeYear: true,
        maxDate: '0',
        onClose: function (selectedDate) {
            jQuery("#sales_analytics_to").datepicker("option", "minDate", selectedDate);
        }
    });
    jQuery("#sales_analytics_to").datepicker({
        dateFormat: 'mm/dd/yy',
        changeMonth: true,
        changeYear: true,
        maxDate: '0',
        onClose: function (selectedDate) {
            jQuery("#sales_analytics_from").datepicker("option", "maxDate", selectedDate);
        }
    });
    // $("#sales_analytics_from").datepicker();
    // $("#sales_analytics_to").datepicker();
    jQuery("#manager_analytics_from").datepicker({
        dateFormat: 'mm/dd/yy',
        changeMonth: true,
        changeYear: true,
        maxDate: '0',
        onClose: function (selectedDate) {
            jQuery("#manager_analytics_to").datepicker("option", "minDate", selectedDate);
        }
    });
    jQuery("#manager_analytics_to").datepicker({
        dateFormat: 'mm/dd/yy',
        changeMonth: true,
        changeYear: true,
        maxDate: '0',
        onClose: function (selectedDate) {
            jQuery("#manager_analytics_from").datepicker("option", "maxDate", selectedDate);
        }
    });
    // $("#manager_analytics_from").datepicker();
    // $("#manager_analytics_to").datepicker();
    $(document).on('click', "#edit_deal_proposal_due_date_other", function () {
        $(this).datepicker({
            minDate: new Date({ dateFormat: 'dd-mm-yy' })
        });
    });

    $('[data-toggle="tooltip"]').tooltip();

    // datatable
    // $(document).on('mouseenter', '#dataTable_other', function() {
    //   $(this).DataTable( {
    //     "bPaginate": true,
    //     "bLengthChange": true,
    //     "bFilter": true,
    //     "bInfo": false,
    //     "bAutoWidth": true
    //   });
    // });

    // pipeline settings functions
    $(document).on('click', '#settings', function (e) {
        $("#sortable_pipeline_stages").sortable();
    });

    // pipeline new stage add
    $(document).on('submit', '#frm_settings_add', function (event) {
        event.preventDefault(); //prevent default action
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        var str = $('#new_stage').val();
        if (/^[a-zA-Z0-9- ]*$/.test(str) == false) {
            $("#server-result_settings").html('Your Stage name contains illegal characters.');
        }

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            dataType: "json",
            cache: false,
            processData: false,
        }).done(function (response) { //
            // console.log(response);
            var response_msg = 'Pipeline not updated.';
            if (response['status'] == '200') {
                response_msg = 'Pipeline successfully updated.';
                $("#server-result_settings").html(response_msg);
                $("#sortable_pipeline_stages").append(response['data']);
                // $('#new_stage').val('Add new stage here');
                // need to reload the page
                window.setTimeout(function () {
                    location.reload();
                }, 1000);

            } else if (response['status'] == '409') {
                response_msg = 'Pipeline not updated.';
                $("#server-result_settings").html(response_msg);

            } else {
                $("#server-result_settings").html(response_msg);
            }
        });
    });

    // pipeline specific stage close and delete
    $(document).on('click', '.stage-close', function (event) {
        event.preventDefault(); //prevent default action
        $(this).parent().hide();
        // id of the removed stage
        var id = $(this).attr("id");

        // call ajax to save it
        var post_url = $('#frm_settings_add').attr("action"); //get form action url
        var request_method = $('#frm_settings_add').attr("method"); //get form GET/POST method
        var formData = [];

        formData.push({
            'change': 'delete',
            'csrf': $('#csrf').val(),
            'stage_id': id
        });
        // for (var p of formData) {
        //   console.log(p);
        // }
        $.ajax({
            url: post_url,
            type: request_method,
            data: {
                'data': formData
            },
            dataType: "json"
        }).done(function (response) { //
            var response_msg = 'You can add edit stages by dragging it to new position';
            if (response['status'] == '200') {
                response_msg = 'Pipeline successfully deleted.';
                $("#server-result_settings").html(response_msg);
                // $("#sortable_pipeline_stages").append(response['data']);
                // $('#new_stage').val('Add new stage here');
                // $("<div>" + response_msg + "</div>").dialog();
            } else if (response['status'] == '409') {
                response_msg = 'Pipeline not deleted. ';
                $("#server-result_settings").html(response_msg);
                // $("<div>" + response_msg + "</div>").dialog();
            } else {
                $("#server-result_settings").html(response_msg);
                // $("<div>" + response_msg + "</div>").dialog();
            }
        }).fail(function (e) {
            console.log(e);
        });
    });

    // whenever pipeline stage changes its position
    // this function will be called
    $("#sortable_pipeline_stages").sortable({
        update: function (event, ui) {
            event.preventDefault();
            var stage_ids = [];
            var stage_values = [];
            $('#sortable_pipeline_stages li').each(function (i) {
                stage_ids.push($(this).attr('id'));
                // stage_values.push($(this).text().slice(0, -1))
                stage_values.push($(this).attr('data'));
            });
            stage_ids = stage_ids.join('#');
            stage_values = stage_values.join('#');

            // call ajax to save it
            var post_url = $('#frm_settings_add').attr("action"); //get form action url
            var request_method = $('#frm_settings_add').attr("method"); //get form GET/POST method
            var formData = [];

            formData.push({
                'change': 'update',
                'csrf': $('#csrf').val(),
                'stage_ids': stage_ids,
                'stage_values': stage_values
            });
            // for (var p of formData) {
            //   console.log(p);
            // }
            $.ajax({
                url: post_url,
                type: request_method,
                data: {
                    'data': formData
                },
                dataType: "json"
            }).done(function (response) { //
                var response_msg = 'You can add edit stages by dragging it to new position';
                if (response['status'] == '200') {
                    response_msg = 'Pipeline successfully updated.';
                    $("#server-result_settings").html(response_msg);
                    // $("#sortable_pipeline_stages").append(response['data']);
                    // $('#new_stage').val('Add new stage here');
                    // $("<div>" + response_msg + "</div>").dialog();
                } else if (response['status'] == '409') {
                    response_msg = 'Pipeline not updated. Existing Deals are in stages can\'t move';
                    $("#server-result_settings").html(response_msg);
                    // $("<div>" + response_msg + "</div>").dialog();
                } else {
                    $("#server-result_settings").html(response_msg);
                    // $("<div>" + response_msg + "</div>").dialog();
                }
            }).fail(function (e) {
                console.log(e);
            });
        }
    });

    // when closing the pipeline settings modal call ajax to save the current pipeline stages
    // modal is in /manager/sidebar.html.twig
    // $(document).on("click", "#close_button_settings_modal", function(event) {
    //     event.preventDefault();
    //     var stage_ids = [];
    //     var stage_values = [];
    //     $('#sortable_pipeline_stages li').each(function(i) {
    //         stage_ids.push($(this).attr('id'));
    //         // stage_values.push($(this).text().slice(0, -1))
    //         stage_values.push($(this).attr('data'));
    //     });
    //     stage_ids = stage_ids.join('#');
    //     stage_values = stage_values.join('#');

    //     // call ajax to save it
    //     var post_url = $('#frm_settings_add').attr("action"); //get form action url
    //     var request_method = $('#frm_settings_add').attr("method"); //get form GET/POST method
    //     var formData = [];

    //     formData.push({
    //         'change': 'update',
    //         'csrf': $('#csrf').val(),
    //         'stage_ids': stage_ids,
    //         'stage_values': stage_values
    //     });
    //     // for (var p of formData) {
    //     //   console.log(p);
    //     // }
    //     $.ajax({
    //         url: post_url,
    //         type: request_method,
    //         data: {
    //             'data': formData
    //         },
    //         dataType: "json",
    //         cache: false,
    //         processData: false,
    //     }).done(function(response) { //
    //         var response_msg = 'You can add edit stages by dragging it to new position';
    //         if (response['status'] == '200') {
    //             response_msg = 'Pipeline successfully updated.';
    //             $("#server-result_settings").html(response_msg);
    //             // $("#sortable_pipeline_stages").append(response['data']);
    //             // $('#new_stage').val('Add new stage here');
    //             // $("<div>" + response_msg + "</div>").dialog();
    //         } else if (response['status'] == '409') {
    //             response_msg = 'Pipeline not updated. Existing Deals are in stages can\'t move';
    //             $("#server-result_settings").html(response_msg);
    //             // $("<div>" + response_msg + "</div>").dialog();
    //         } else {
    //             $("#server-result_settings").html(response_msg);
    //             // $("<div>" + response_msg + "</div>").dialog();
    //         }
    //     }).fail(function(e) {
    //         console.log(e);
    //     });
    // });

    // call pipeline setting update
    // $(document).on("click", ".stage-close", function(event) {
    //     var stage = $(this).attr("id");
    //     if (confirm('Are you sure to delete this stage?')) {
    //         event.preventDefault();
    //         // call ajax to save it
    //         var post_url = $('#frm_settings_add').attr("action"); //get form action url
    //         var request_method = $('#frm_settings_add').attr("method"); //get form GET/POST method
    //         var formData = [];

    //         formData.push({
    //             'change': 'delete',
    //             'csrf': $('#csrf').val(),
    //             'stage_id': stage
    //         });

    //         $.ajax({
    //             url: post_url,
    //             type: request_method,
    //             data: {
    //                 'data': formData
    //             },
    //             dataType: "json",
    //             cache: false,
    //             processData: false,
    //         }).done(function(response) { //

    //             var response_msg = 'You can add edit stages by dragging it to new position';
    //             if (response['status'] == '200') {
    //                 response_msg = 'Pipeline stage successfully deleted.';
    //                 $("#server-result_settings").html(response_msg);

    //                 $('#' + stage).remove();
    //                 $('#' + stage).parent().hide();

    //             } else if (response['status'] == '409') {
    //                 response_msg = 'This Pipeline stage could not be deleted. Related deals exists with this stage.';
    //                 $("#server-result_settings").html(response_msg);

    //             } else {
    //                 $("#server-result_settings").html(response_msg);
    //             }
    //         }).fail(function(e) {
    //             console.log(e);
    //         });
    //     }
    // });

    // sales rep invitation forms submit functions form created in add_sales_rep
    $("#frm_invite_sales_rep").submit(function (event) {
        $("#frm_invite_sales_rep_submit").attr("disabled", true); // submit btn disabled
        var response = 'Please wait while validating, processing and sending mail.';
        $("#frm_invite_sales_rep_server-results").html(response);

        event.preventDefault(); //prevent default action
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            $("#frm_invite_sales_rep_submit").attr("disabled", false);

            if (response == '200') {
                response = 'Invitation successfully sent. Request her/him to check her/his email.';
                $('#frm_invite_sales_rep_server-results').toggleClass('alert-warning alert-success');
                $("#frm_invite_sales_rep_server-results").html(response);
                $("#frm_invite_sales_rep")[0].reset();
            } else if (response == '409') {
                response = 'Email already exist. Kindly contact to admin.';
                $("#frm_invite_sales_rep_server-results").html(response);
            } else {
                response = 'Invitation not able to sent.';
                $("#frm_invite_sales_rep_server-results").html(response);
            }
        });
    });

    // setup posted value for prepare chart with actual from and to date
    $('#sales_chart_submit').click(function (event) {
        $('#posted_sales_analytics_from').val($('#sales_analytics_from').val());
        $('#posted_sales_analytics_to').val($('#sales_analytics_to').val());
    });

    // this will generate chart in dashboard of sales rep
    $('#myAreaChartSales').ready(function (event) {

        // ajax call to fetch data
        var post_url = '/sales/get_chart_data_for_dashboard'; //get form action url
        var request_method = 'post'; //get form GET/POST method
        var form_data = '';
        if (typeof $('#posted_sales_analytics_from').val() !== "undefined" && typeof $('#posted_sales_analytics_to').val() !== "undefined") {
            var form_data = new FormData();
            form_data.append('analytics_from', $('#posted_sales_analytics_from').val());
            form_data.append('analytics_to', $('#posted_sales_analytics_to').val());
        }

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            processData: false,
            contentType: false,
            // dataType: 'json',
        }).done(function (response) {
            // to check that it is a json string
            if (response.charAt(0) != '<') {
                response = $.parseJSON(response);
                if (response.status == '200') {
                    // Set new default font family and font color to mimic Bootstrap's default styling
                    Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
                    Chart.defaults.global.defaultFontColor = '#292b2c';

                    // Area Chart Example
                    var ctx = document.getElementById("myAreaChartSales");
                    if (ctx !== null) {
                        ctx = ctx.getContext('2d');
                        // create chart data
                        var dates = new Array();
                        var budgets = new Array();
                        var max_budget = 0;
                        var currency = response.data.currency;
                        $.each(response.data, function (key, value) {
                            if (max_budget < value.budget) {
                                max_budget = parseInt(value.budget);
                            }
                            dates.push(value.created_at);
                            budgets.push(value.budget);
                        });

                        var myLineChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: dates,
                                datasets: [{
                                    label: "Budget",
                                    lineTension: 0.3,
                                    backgroundColor: "rgba(2,117,216,0.2)",
                                    borderColor: "rgba(2,117,216,1)",
                                    pointRadius: 5,
                                    pointBackgroundColor: "rgba(2,117,216,1)",
                                    pointBorderColor: "rgba(255,255,255,0.8)",
                                    pointHoverRadius: 5,
                                    pointHoverBackgroundColor: "rgba(2,117,216,1)",
                                    pointHitRadius: 50,
                                    pointBorderWidth: 2,
                                    data: budgets,
                                }],
                            },
                            options: {
                                scales: {
                                    xAxes: [{
                                        time: {
                                            unit: 'date'
                                        },
                                        gridLines: {
                                            display: false
                                        },
                                        ticks: {
                                            maxTicksLimit: 15
                                        }
                                    }],
                                    yAxes: [{
                                        ticks: {
                                            min: 0,
                                            max: max_budget,
                                            maxTicksLimit: 15
                                        },
                                        gridLines: {
                                            color: "rgba(0, 0, 0, .125)",
                                        }
                                    }],
                                },
                                legend: {
                                    display: true
                                }
                            }
                        });

                    } else {
                        var response = 'No data found.';
                        console.log(response);
                    }
                }
            }
        });
    });

    // this will generate chart in dashboard of manager
    $('#myAreaChartManager').ready(function (event) {

        // ajax call to fetch data
        var post_url = '/manager/get_chart_data_for_dashboard'; //get form action url
        var request_method = 'post'; //get form GET/POST method
        var form_data = ''; //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) {
            // to check that it is a json string
            if (response.charAt(0) != '<') {

                response = $.parseJSON(response);
                if (response.status == '200') {
                    // Set new default font family and font color to mimic Bootstrap's default styling
                    Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
                    Chart.defaults.global.defaultFontColor = '#292b2c';

                    // Area Chart Example
                    var ctx = document.getElementById("myAreaChartManager");
                    if (ctx !== null) {
                        ctx = ctx.getContext('2d');
                        // create chart data
                        var dates = new Array();
                        var budgets = new Array();
                        var max_budget = 0;
                        var currency = response.data.currency;
                        $.each(response.data, function (key, value) {
                            if (max_budget < value.budget) {
                                max_budget = parseInt(value.budget);
                            }
                            dates.push(value.created_at);
                            budgets.push(value.budget);
                        });

                        var myLineChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: dates,
                                datasets: [{
                                    label: "Budget",
                                    lineTension: 0.3,
                                    backgroundColor: "rgba(2,117,216,0.2)",
                                    borderColor: "rgba(2,117,216,1)",
                                    pointRadius: 5,
                                    pointBackgroundColor: "rgba(2,117,216,1)",
                                    pointBorderColor: "rgba(255,255,255,0.8)",
                                    pointHoverRadius: 5,
                                    pointHoverBackgroundColor: "rgba(2,117,216,1)",
                                    pointHitRadius: 50,
                                    pointBorderWidth: 2,
                                    data: budgets,
                                }],
                            },
                            options: {
                                scales: {
                                    xAxes: [{
                                        time: {
                                            unit: 'date'
                                        },
                                        gridLines: {
                                            display: false
                                        },
                                        ticks: {
                                            maxTicksLimit: 15
                                        }
                                    }],
                                    yAxes: [{
                                        ticks: {
                                            min: 0,
                                            max: max_budget,
                                            maxTicksLimit: 15
                                        },
                                        gridLines: {
                                            color: "rgba(0, 0, 0, .125)",
                                        }
                                    }],
                                },
                                legend: {
                                    display: true
                                }
                            }
                        });

                    } else {
                        var response = 'No data found.';
                        // console.log(response);
                    }
                }
            }
        });
    });


    // this will generate chart in dashboard of manager from submit date range
    $('#myAreaChartManagerPost').ready(function (event) {
        var pathname = window.location.pathname;
        // posting different date range call for chart
        if (pathname == '/manager/analytics') {
            // ajax call to fetch data
            var post_url = '/manager/get_chart_data_for_dashboard'; //get form action url
            var request_method = 'post'; //get form GET/POST method
            var form_data = ''; //Encode form elements for submission

            $.ajax({
                url: post_url,
                type: request_method,
                data: form_data,
                cache: false,
                processData: false,
            }).done(function (response) {
                // to check that it is a json string
                if (response.charAt(0) != '<') {

                    response = $.parseJSON(response);
                    if (response.status == '200') {
                        // Set new default font family and font color to mimic Bootstrap's default styling
                        Chart.defaults.global.defaultFontFamily = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
                        Chart.defaults.global.defaultFontColor = '#292b2c';

                        // Area Chart Example
                        var ctx = document.getElementById("myAreaChartManagerPost");
                        if (ctx !== null) {
                            ctx = ctx.getContext('2d');
                            // create chart data
                            var dates = new Array();
                            var budgets = new Array();
                            var max_budget = 0;
                            var currency = response.data.currency;
                            $.each(response.data, function (key, value) {
                                if (max_budget < value.budget) {
                                    max_budget = parseInt(value.budget);
                                }
                                dates.push(value.created_at);
                                budgets.push(value.budget);
                            });

                            var myLineChart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: dates,
                                    datasets: [{
                                        label: "Budget",
                                        lineTension: 0.3,
                                        backgroundColor: "rgba(2,117,216,0.2)",
                                        borderColor: "rgba(2,117,216,1)",
                                        pointRadius: 5,
                                        pointBackgroundColor: "rgba(2,117,216,1)",
                                        pointBorderColor: "rgba(255,255,255,0.8)",
                                        pointHoverRadius: 5,
                                        pointHoverBackgroundColor: "rgba(2,117,216,1)",
                                        pointHitRadius: 50,
                                        pointBorderWidth: 2,
                                        data: budgets,
                                    }],
                                },
                                options: {
                                    scales: {
                                        xAxes: [{
                                            time: {
                                                unit: 'date'
                                            },
                                            gridLines: {
                                                display: false
                                            },
                                            ticks: {
                                                maxTicksLimit: 15
                                            }
                                        }],
                                        yAxes: [{
                                            ticks: {
                                                min: 0,
                                                max: max_budget,
                                                maxTicksLimit: 15
                                            },
                                            gridLines: {
                                                color: "rgba(0, 0, 0, .125)",
                                            }
                                        }],
                                    },
                                    legend: {
                                        display: true
                                    }
                                }
                            });

                        } else {
                            response = 'No data found.';
                            console.log(response);
                        }
                    }
                }
            });
        }
    });



    // login get login code
    $("#frm_login").submit(function (event) {
        event.preventDefault(); //prevent default action
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission
        $('#btn_login').attr('disabled', true);

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            $('#btn_login').attr('disabled', false);
            if (response == '200') {
                response = 'Login verification code successfully sent. Kindly check your email. Do not close this Login form. Here you have to enter the code to login.';
                $('#login_result').removeClass();
                $('#login_result').addClass('alert');
                $('#login_result').addClass('alert-success');
                $("#login_result").html(response);

                // hide this form get login verification code form
                $('#login_code_form').hide();
                // empty whole get login verification code form
                $('#login_code_form').empty();
                // show the main Login form
                $('#login_form').show();
            } else if (response == '201') {
                response = 'Login verification code successfully sent few minutes back. Kindly check your email. Do not close this Login form. Here you have to enter the code to login. If you do not get email kindly wait for few minute and try again.';
                $('#login_result').removeClass();
                $('#login_result').addClass('alert-success');
                $("#login_result").html(response);

                // hide this form get login verification code form
                $('#login_code_form').hide();
                // empty whole get login verification code form
                $('#login_code_form').empty();
                // show the main Login form
                $('#login_form').show();

            } else if (response == '409') {
                $('#login_result').toggleClass('alert-warning alert-danger');
                response = 'Email or Password not matched.';
                $("#login_result").html(response);
            } else if (response == '202') {
                location.reload();
            } else {
                response = 'Invitation not able to sent.';
                $("#login_result").html(response);
            }
        });
    });

    // move a deal to won
    $("#frm_deal_won").submit(function (event) {
        event.preventDefault(); //prevent default action
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            if (response == '200') {
                response = 'Congratulation you won the deal also your feed back has been submitted successfully.';
                $('#deal_won_result').addClass('alert-success');
                $("#frm_deal_won").trigger("reset");
                $("#deal_won_result").html(response);

                window.setTimeout(function () {
                    location.reload();
                }, 1000);
            } else {
                response = 'Could not saved.';
                $('#deal_won_result').addClass('alert-danger');
                $("#frm_deal_won").trigger("reset");
                $("#deal_won_result").html(response);
                window.setTimeout(function () {
                    //location.reload();
                }, 1000);
            }

        });
    });

    // move a deal to lost
    $("#frm_deal_lost").submit(function (event) {
        event.preventDefault(); //prevent default action
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            if (response == '200') {
                response = 'Your feed back has been submitted successfully.';
                $('#deal_lost_result').addClass('alert-success');
                $("#frm_deal_lost").trigger("reset");
                $("#deal_lost_result").html(response);
                window.setTimeout(function () {
                    location.reload();
                }, 1000);
            } else {
                response = 'Feed back could not saved.';
                $('#deal_lost_result').addClass('alert-danger');
                $("#frm_deal_lost").trigger("reset");
                $("#deal_lost_result").html(response);
                window.setTimeout(function () {
                    //location.reload();
                }, 1000);
            }

        });
    });

    // show push notification for sales admin
    $('#notification').ready(function () {
        var url = $(location).attr("href"),
            parts = url.split("/"),
            last_part = parts[parts.length - 1];

        // run only when /sales
        if (last_part == 'sales') {
            var post_url = '/sales/show_notification'; //get form action url
            var request_method = 'post'; //get form GET/POST method

            $.ajax({
                url: post_url,
                type: request_method,
                cache: false,
                processData: false,
            }).done(function (response) { //
                if (response != '') {
                    $('#notification').append(response);
                    $(".toast").toast({
                        autohide: false
                    });
                    $('.toast').toast('show');
                }
            });
        }
    });

    // csv File type validation
    $("#frm_csv_import #csv_import").change(function () {
        var file = this.files[0];
        var fileType = file.type;
        var match = ['application/vnd.ms-excel', 'application/csv', 'text/csv'];
        if (!((fileType == match[0]) || (fileType == match[1]) || (fileType == match[2]))) {
            $("#contacts_csv_upload_response").html('Sorry, only CSV file is allowed to upload.');
            $(this).val('');
            return false;
        }
        // can be uploaded upto 500KB
        if (this.files[0].size > 500000) {
            $(this).val('');
            $("#frm_csv_import #contacts_csv_upload_response").html('Sorry, file size more, kindly upload upto 500KB only');
            return false;
        }

        $("#contacts_csv_upload_response").html('Click on Import to upload and process.');
    });

    // contacts csv upload
    $("#frm_csv_import").on('submit', function (event) {
        event.preventDefault(); //prevent default action
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = new FormData(this);

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            contentType: "application/octet-stream",
            enctype: 'multipart/form-data',
            contentType: false,
            processData: false,
            beforeSend: function () {
                $('#contacts_csv_upload_submit').attr("disabled", "disabled");
                $('#frm_csv_import').css("opacity", ".5");
            }
        }).done(function (response) { //
            if (response == '200') {
                response = 'Your Contacts successfully uploaded.';
                $("#contacts_csv_upload_response").html(response);
                // window.setTimeout(function() {
                //     location.reload();
                // }, 1000);
            } else if (response == 409) {
                response = 'Sorry you can\'t upload more contacts at a time. Kindly contact to support.';
                $("#contacts_csv_upload_response").html(response);
                // window.setTimeout(function() {
                //     location.reload();
                // }, 1000);

            } else {
                response = 'Kindly follow as per demo CSV file. Contacts could not uploaded.';
                $("#contacts_csv_upload_response").html(response);
                // window.setTimeout(function() {
                //     location.reload();
                // }, 1000);
            }
            $('#frm_csv_import').css("opacity", "");
            $("#contacts_csv_upload_submit").removeAttr("disabled");
        });
    });

    // fetch task details while click to get modal to send email
    $('.open_task_email_modal').click(function () {
        var task_id = $(this).data('id');
        $("input#email_task_id").val(task_id);
        $('#send_task_email_modal').modal('show');
    });

    // send mail by user's gmail and create marking label in gmail as well
    $('#frm_send_task_email').on('submit', function (event) {
        event.preventDefault(); //prevent default action
        $('#send_task_email_submit').attr('disabled', true);
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission
        var hidden_values = form_data.split('&');
        hidden_values = hidden_values[1].split('=');
        var note_id = hidden_values[1];

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) {
            $('#send_task_email_submit').attr('disabled', false);
            if (response == '200') {
                var response_display = 'Mail sent successfully. All Task Status as Label and Deal as Sub-label created into your Gmail.';
                $("#server-result_send_email").removeClass('alert-danger');
                $("#server-result_send_email").removeClass('alert-warning');
                $("#server-result_send_email").addClass('alert-success');
                $("#server-result_send_email").html(response_display);
                setTimeout(
                    function () {
                        $("#frm_send_task_email").trigger("reset");
                        $("#task_email_body").val('');
                        $('#send_task_email_modal').modal('toggle');
                        // location.reload();
                    }, 1000);
            } else {
                $("#server-result_send_email").addClass('alert-danger');
                response = 'Mail could not sent.';
                $("#server-result_send_email").html(response);
            }
        });
    });

    // fetch task edit details while click to get modal
    $('.sales_task_edit_modal_open').click(function () {
        var task_id = $(this).data('id');
        $("input#task_edit_id").val(task_id);

        // call ajax to fill all fields
        var post_url = '/sales/get_task_details'; //get form action url
        var request_method = 'post'; //get form GET/POST method

        $.ajax({
            url: post_url,
            type: request_method,
            data: {
                'task_id': task_id
            }
        }).done(function (response) { //
            if (response != '') {
                // need to update all fields in the modal form to update
                response = $.parseJSON(response)[0];
                $("#edit_task_modal #todo_detail").val(response.Notes);
                $("#edit_task_modal #task_update").val(response.Task_Update);
                $('#edit_task_modal .form-check-input').each(function (i, obj) {
                    if ('todo_desc_' + response.Todo_Desc_ID == obj.id) {
                        $(obj).prop('checked', true);
                    }
                });
                $("#todo_status > [value=" + response.Task_Status + "]").attr("selected", "true");
                var due_date_time = response.Todo_Due_Date;

                var due_date = due_date_time.substr(0, 10);
                var due_time = due_date_time.substr(11);
                var times = due_time.split(' to ');
                var start_time = null;
                var end_time = null;
                var duration = null;

                // for both start and end time
                var cnt = 0;
                times.forEach(element => {
                    // for start time
                    if (cnt == 0) {
                        var temp = element.split(' ');
                        if (temp[1] == 'am') {
                            if (temp[0].length < 5) {
                                due_time = '0' + temp[0];
                            }
                            start_time = due_time;
                        } else { // for pm need to convert to 24 hour format
                            if (temp[0].length < 5) {
                                var due_time_temp = temp[0].split(':');
                                start_time = parseInt(due_time_temp[0]) + 12;
                                temp[0] = start_time + ':' + due_time_temp[1];
                            }
                            start_time = temp[0];
                        }
                    } else { // for end time
                        var temp = element.split(' ');
                        if (temp[1] == 'am') {
                            if (temp[0].length < 5) {
                                due_time = '0' + temp[0];
                            }
                            end_time = due_time;
                        } else { // for pm need to convert to 24 hour format
                            if (temp[0].length < 5) {
                                var due_time_temp = temp[0].split(':');
                                end_time = parseInt(due_time_temp[0]) + 12;
                                temp[0] = end_time + ':' + due_time_temp[1];
                            }
                            end_time = temp[0];
                        }
                    }
                    cnt += 1;
                });

                var diff = (new Date("1970-1-1 " + end_time) - new Date("1970-1-1 " + start_time)) / 1000 / 60;

                var duration;
                if (diff > 59) {
                    var hour = diff / 60;
                    hour = hour < 10 ? '0' + hour : hour;
                    var min = diff % 60;
                    min = min < 10 ? '0' + min : min;
                    duration = hour + ':' + min;
                } else {
                    duration = '00:' + diff;
                }

                // set selected option for duration
                $('#edit_task_modal #duration option[value=' + diff + ']', duration).attr('selected', 'selected');

                $('#edit_task_modal #todo_due_date_edit').val(due_date);
                $('#edit_task_modal #todo_due_time').val(start_time);
                $('#edit_task_modal #duration').val(duration);
                $('#edit_task_modal #deal').val(response.Deal);
                // existing data require for validate that anything updated or not before sending to server
                var existing_data = [];
                var item = {};
                item['csrf'] = $('#edit_task_modal #csrf').val();
                item['stage'] = $('#edit_task_modal #stage').val();
                item['task_edit_id'] = $('#edit_task_modal #task_edit_id').val();
                item['todo_detail'] = response.Notes;
                item['task_update'] = response.Task_Update;
                item['todo_due_date_edit'] = due_date;
                item['todo_due_time'] = due_time;
                item['duration'] = duration;
                item['deal'] = response.Deal;
                existing_data.push(item);
                $('#edit_task_modal #existing_data').val(JSON.stringify(existing_data));

                $('#edit_task_modal').modal('show');
            }
        });
    });

    // update the task becuase it is modal can't call direct submit on button
    $(document).on("click", "#todo_update_submit", function (event) {
        // call ajax to save it
        event.preventDefault();
        $('#todo_update_submit').attr('disabled', true);
        var post_url = $('#frm_edit_task').attr("action"); //get form action url
        var request_method = $('#frm_edit_task').attr("method"); //get form GET/POST method
        var existing_data = $.parseJSON($('#edit_task_modal #existing_data').val());

        var formData = [];
        if ($('#edit_task_modal #task_update').val() == '') {
            $("#server-result_todo_edit").toggleClass('alert-danger');
            var response = 'Task could not updated. Fill all the required fields first.';
            $("#server-result_todo_edit").html(response);
            $('#todo_update_submit').attr('disabled', false);
            return false;
        }

        formData.push({
            'csrf': $('#edit_task_modal #csrf').val(),
            'stage': $('#edit_task_modal #stage').val(),
            'task_edit_id': $('#edit_task_modal #task_edit_id').val(),
            'todo_detail': $('#edit_task_modal #todo_detail').val(),
            'todo_status': $('#edit_task_modal #todo_status').val(),
            'task_update': $('#edit_task_modal #task_update').val(),
            'todo_due_date': $('#edit_task_modal #todo_due_date_edit').val(),
            'todo_due_time': $('#edit_task_modal #todo_due_time').val(),
            'duration': $('#edit_task_modal #duration').val(),
            'deal': $('#edit_task_modal #deal').val(),
            'owner': $('#edit_task_modal #owner').val()
        });

        var form_data = $.parseJSON(JSON.stringify(formData));
        // validate that existing data and change data are not same
        var result = diff(formData[0], existing_data[0]);

        if (result > 0) {
            // call for update
            $.ajax({
                url: post_url,
                type: request_method,
                data: {
                    'data': formData[0]
                },
                dataType: "json"
            }).done(function (response) { //
                $('#todo_update_submit').attr('disabled', false);
                if (response == '200') {
                    $("#server-result_todo_edit").addClass('alert-success');
                    response = 'Your Task successfully updated.';
                    $("#server-result_todo_edit").html(response);
                    window.setTimeout(function () {
                        location.reload();
                    }, 1000);
                } else if (response == 409) {
                    $("#server-result_todo_edit").addClass('alert-danger');
                    response = 'Task could not updated.';
                    $("#server-result_todo_edit").html(response);
                    window.setTimeout(function () {
                        //location.reload();
                    }, 1000);

                } else {
                    $("#server-result_todo_edit").addClass('alert-danger');
                    response = 'Kindly check the details.';
                    $("#server-result_todo_edit").html(response);
                    window.setTimeout(function () {
                        //location.reload();
                    }, 1000);
                }
            });
        } else {
            var response = 'All data are same. Nothing to update.';
            $("#edit_task_modal #server-result_todo_edit").html(response);
            window.setTimeout(function () {
                $('#edit_task_modal').modal('hide');
            }, 1000);
        }
    });

    // ajax call to update Deal select box on Add - To Do tab
    $('#frm_add_todo #get_select_deals').click(function (event) {
        event.preventDefault();
        $('#frm_add_todo #get_select_deals').hide();
        var post_url = '/sales/get_deals'; //get form action url
        var request_method = 'POST';

        $.ajax({
            url: post_url,
            type: request_method,
            data: {},
            dataType: "json",
            cache: false,
            processData: false,
        }).done(function (response) { //
            if (response['status'] == '200') {
                // console.log(response['data']);
                $('#frm_add_todo #deal').show();
                var obj = response['data'];
                $('#frm_add_todo #deal')
                    .find('option')
                    .remove();
                $.each(obj, function (key, value) {
                    // alert(key + ": " + value.id);
                    $('#frm_add_todo #deal')
                        .append('<option value="' + value.id + '">' + value.project_type + '</option>');
                });

            } else {
                response = 'No Deal.'; // $("#server-result_todo_edit").html(response);
            }
        });
    });

    // delete a contact along with its address
    $(document).on('submit', "#frm_data_contact_delete", function (event) {
        event.preventDefault(); //prevent default action
        var r = confirm("Are you sure to hide this contact?");
        if (r != true) {
            return false;
        }

        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            if (response == '200') {
                // console.log('200');
                // need to reload the page
                location.reload();
            } else if (response == '409') {
                // console.log('409');
                return false;
            } else {
                // console.log('400');
                return false;
            }
        });
    });

    // delete a contact along with its address
    $(document).on('submit', "#frm_data_sales_task_delete", function (event) {
        event.preventDefault(); //prevent default action
        var r = confirm("Are you sure to update this task as completed?");
        if (r != true) {
            return false;
        }

        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            if (response == '200') {
                // console.log('200');
                // need to reload the page
                location.reload();
            } else if (response == '409') {
                // console.log('409');
                return false;
            } else {
                // console.log('400');
                return false;
            }
        });
    });

    // edit and update a contact details along with its address
    $(document).on('submit', "#frm_edit_contact", function (event) {
        event.preventDefault(); //prevent default action

        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        var data = form_data_serialize_to_assoc_array(form_data);
        var original_data = form_data_serialize_to_assoc_array(data.edit_contact_original_values);

        var id = null;
        var data_changed = 0;
        // checking whether user changed the data or false submit
        $(original_data).each(function (index, item) {
            if (item.address1 != data.address1) {
                data_changed = 1;
                return false;
            }
            if (item.background != data.background) {
                data_changed = 1;
                return false;
            }
            if (item.city != data.city) {
                data_changed = 1;
                return false;
            }
            if (item.company != data.company) {
                data_changed = 1;
                return false;
            }
            if (item.country != data.country) {
                data_changed = 1;
                return false;
            }
            if (item.designation != data.designation) {
                data_changed = 1;
                return false;
            }
            if (item.email != data.email) {
                data_changed = 1;
                return false;
            }
            if (item.first != data.first) {
                data_changed = 1;
                return false;
            }
            if (item.last != data.last) {
                data_changed = 1;
                return false;
            }
            if (item.phone != data.phone) {
                data_changed = 1;
                return false;
            }
            if (item.state != data.state) {
                data_changed = 1;
                return false;
            }
            if (item.street != data.street) {
                data_changed = 1;
                return false;
            }
            if (item.title != data.title) {
                data_changed = 1;
                return false;
            }
            if (item.website != data.website) {
                data_changed = 1;
                return false;
            }
            if (item.zip != data.zip) {
                data_changed = 1;
                return false;
            }
            if (item.lead_referral_source != data.lead_referral_source) {
                data_changed = 1;
                return false;
            }
            if (item.industry != data.industry) {
                data_changed = 1;
                return false;
            }
        });

        // need to update
        if (data_changed == 1) {

            $.ajax({
                url: post_url,
                type: request_method,
                data: form_data,
                cache: false,
                processData: false,
            }).done(function (response) { //
                if (response == '200') {
                    // console.log('200');
                    $("#server-results_edit_contact").removeClass('alert-warning');
                    $("#server-results_edit_contact").addClass('alert-success');
                    response = 'Contact successfully updated.';
                    $("#server-results_edit_contact").html(response);
                    window.setTimeout(function () {
                        location.reload();
                    }, 1000);
                } else if (response == '409') {
                    // console.log('409');
                    response = 'Contact not updated.';
                    $("#server-results_edit_contact").addClass('alert-danger');
                    $("#server-results_edit_contact").html(response);

                } else {
                    // console.log('400');
                    response = 'Contact not updated.';
                    $("#server-results_edit_contact").addClass('alert-danger');
                    $("#server-results_edit_contact").html(response);

                }
            });
        } else {
            $("#server-results_edit_contact").toggleClass('alert-warning');
            var response = 'Contact not updated. No changes found.';
            $("#server-results_edit_contact").html(response);
        }
    });

    // change password from sales/menu.html.twig
    $("#frm_change_password").submit(function (event) {
        event.preventDefault(); //prevent default action
        $('#change_password_Button').attr('disabled', true);
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //

            if (response == '200') {
                response = 'Password successfully updated.';
                $("#server-results_change_password").removeClass('alert-warning');
                $("#server-results_change_password").addClass('alert');
                $("#server-results_change_password").addClass('alert-success');
                $("#server-results_change_password").html(response);
                window.setTimeout(function () {
                    $('#change_password_Button').attr('disabled', false);
                    // $('#change_passwordModal').modal("hide");
                    location.reload();
                }, 1000);

            } else if (response == '409') {
                response = 'Password not updated.';
                $("#server-results_change_password").toggleClass('alert-danger');
                $("#server-results_change_password").html(response);

            } else {
                response = 'Password not updated.';
                $("#server-results_change_password").toggleClass('alert-danger');
                $("#server-results_change_password").html(response);

            }
        });
    });

    // update login with code from sales/menu.html.twig
    $("#frm_login_with_code").submit(function (event) {
        event.preventDefault(); //prevent default action
        $('#update_login_with_code').attr('disabled', true);
        var post_url = $(this).attr("action"); //get form action url
        var request_method = $(this).attr("method"); //get form GET/POST method
        var form_data = $(this).serialize(); //Encode form elements for submission

        $.ajax({
            url: post_url,
            type: request_method,
            data: form_data,
            cache: false,
            processData: false,
        }).done(function (response) { //
            if (response == '200') {
                response = 'Login with code option successfully updated.';
                $("#server-results_login_with_code").removeClass('alert-warning');
                $("#server-results_login_with_code").addClass('alert');
                $("#server-results_login_with_code").addClass('alert-success');
                $("#server-results_login_with_code").html(response);

            } else if (response == '409') {
                response = 'Login with code option not updated.';
                $("#server-results_login_with_code").toggleClass('alert-danger');
                $("#server-results_login_with_code").html(response);

            } else {
                response = 'Login with code option not updated.';
                $("#server-results_login_with_code").toggleClass('alert-danger');
                $("#server-results_login_with_code").html(response);

            }
        });
    });
})(jQuery); // End of use strict


// dynamically fill the note_id into add comment modal form calling from sales/task.html.twig
function fill_note_id(note_id, status) {
    $('#frm_add_comment').find('input[name="note_id"]').val(note_id);
    $('#frm_add_comment').find('input[name="task_status"]').val(status);
    // if status = 2 Completed then add form must not display
    if (status == 'completed') {
        $("#frm_add_comment").hide();
        var response = 'This task is completed. But you can still see the Comment History.';
        $("#server-results_comment").toggleClass('alert-danger');
        $("#server-results_comment").html(response);
    }
    //$('input[type="text"]#note_id').val(note_id);
}

// this will fill the contact details to the edit contact modal form

// Address_Id: "45"
// Background_Info: ""
// Company: "Biz-Keeper"
// Contact_First: "Tanushree"
// Contact_Last: "Patra"
// Contact_Middle: null
// Contact_Title: "Mrs."
// Date_of_Initial_Contact: "2019-12-01"
// Email: "tanusree@gmail.com"
// Industry: "Software"
// Lead_Referral_Source: "google"
// LinkedIn_Profile: null
// Phone: "4657567575"
// Sales_Rep: "3"
// Title: "CEO"
// Website: "biz-keeper.com"
// address: ""
// address_city: "Bangalore"
// address_country: "100"
// address_state: ""
// address_street1: ""
// address_street2: null
// address_zip: "0"
// created_at: "2019-12-01 10:44:48"
// id: "33"
function fill_edit_contact_form(contact) {
    // console.log(contact);
    // set the values to modal form
    // for edit contact modal
    var zip = contact.address_zip == 0 ? '' : contact.address_zip;
    $('#frm_edit_contact').find('input[name="edit_contact_id"]').val(contact.id);
    $('#frm_edit_contact').find('input[name="edit_contact_address_id"]').val(contact.Address_Id);
    $('#frm_edit_contact').find('input[name="title"]').val(contact.Contact_Title);
    $('#frm_edit_contact').find('input[name="first"]').val(contact.Contact_First);
    $('#frm_edit_contact').find('input[name="last"]').val(contact.Contact_Last);
    $('#frm_edit_contact').find('input[name="email"]').val(contact.Email);
    $('#frm_edit_contact').find('input[name="phone"]').val(contact.Phone);
    $('#frm_edit_contact').find('input[name="company"]').val(contact.Company);
    $('#frm_edit_contact').find('input[name="lead_referral_source"]').val(contact.Lead_Referral_Source);
    $('#frm_edit_contact').find('input[name="designation"]').val(contact.Title);
    $('#frm_edit_contact').find('input[name="industry"]').val(contact.Industry);
    $('#frm_edit_contact').find('input[name="website"]').val(contact.Website);
    $('#frm_edit_contact').find('input[name="background"]').val(contact.Background_Info);
    $('#frm_edit_contact').find('input[name="address1"]').val(contact.address);
    $('#frm_edit_contact').find('input[name="street"]').val(contact.address_street1);
    $('#frm_edit_contact').find('input[name="city"]').val(contact.address_city);
    $('#frm_edit_contact').find('input[name="state"]').val(contact.address_state);
    $('#frm_edit_contact').find('input[name="zip"]').val(zip);
    // country selection selected option
    $('#frm_edit_contact #country option[value="' + contact.address_country + '"').attr("selected", "selected").change();
    var original_values = 'edit_contact_address_id=' + contact.Address_Id + '&title=' + contact.Contact_Title + '&first=' + contact.Contact_First + '&last=' + contact.Contact_Last + '&email=' + contact.Email + '&phone=' + contact.Phone + '&company=' + contact.Company + '&lead_referral_source=' + contact.Lead_Referral_Source + '&designation=' + contact.Title + '&industry=' + contact.Industry + '&website=' + contact.Website + '&background=' + contact.Background_Info + '&address1=' + contact.address + '&street=' + contact.address_street1 + '&city=' + contact.address_city + '&state=' + contact.address_state + '&zip=' + zip + '&country=' + contact.address_country + '';

    $('#frm_edit_contact').find('input[name="edit_contact_original_values"]').val(original_values);
}

// this function will move a deal to another stage including won and lost
function move(current, to_move, deal) {
    if (current != to_move) {
        if (confirm("Are you sure you want to move this?")) {
            // show the modal
            if (to_move == 'won') {
                $('#dealwonModal').modal('show');
            } else if (to_move == 'lost') {
                $('#deallostModal').modal('show');
            }

            // set the values to modal form
            // for won deal modal
            if (to_move == 'won') {
                $('#frm_deal_won').find('input[name="own_deal_stage"]').val(current);
                $('#frm_deal_won').find('input[name="own_deal_id"]').val(deal);
            } else if (to_move == 'lost') { // for lost deal
                $('#frm_deal_lost').find('input[name="lost_deal_id"]').val(deal);
            } else {
                // for all other move
                $.post("/sales/move", {
                    current: current,
                    to_move: to_move,
                    deal_id: deal
                },
                    function (data, status) {
                        // alert("Data: " + data + "\nStatus: " + status);
                        // $('#'+ output_div).html(data);
                        if (data == '200' && to_move != 'won' && to_move != 'lost') {
                            window.location.reload();
                        } else { // setting deal id to save feedback after deal won or lost
                            if (to_move == 'won') {
                                $('#deal_id').val(deal);
                            } else if (to_move == 'lost') {
                                $('#lost_deal_id').val(deal);
                            }
                        }
                    });
            }
        } else {
            return false;
        }
    }
}

// convert 12 hours am/pm time format to 24 hours time format
const convertTime12to24 = (time12h) => {
    const time = time12h.substr(0, 6);
    const modifier = time12h.substr(5, 2);

    let [hours, minutes] = time.split(':');

    if (hours === '12') {
        hours = '00';
    }

    if (modifier.toLowerCase === 'pm') {
        hours = parseInt(hours, 10) + 12;
    }

    return `${hours}:${minutes}`;
}

// associative array comparison
function diff(obj1, obj2) {
    // var result = {};
    var result = 0;
    $.each(obj1, function (key, value) {
        if (!obj2.hasOwnProperty(key) || obj2[key] !== obj1[key]) {
            // result[key] = value;
            result += 1;
        }
    });

    return result;
}

// this will loop through serialize form data and create an array
function form_data_serialize_to_assoc_array(form_data) {
    var form_data = form_data.split('&');
    var result = {};
    $(form_data).each(function (index, item) {
        var temp = item.split('=');
        result[temp[0]] = unescape(temp[1]);
    });

    return result;
}