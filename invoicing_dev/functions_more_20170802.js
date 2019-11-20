function get_items(customer_id) {
  throb()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "items.php",
    type : "post",
    data : {
      customer_id : customer_id,
      user_id : window.user_id,
    },
    dataType : "json",
    success : [ajax_result_handler, parse_items],
    complete : unthrob,
    error : [empty_main, ajax_error_handler],
  })
}

function show_checks_pane() {
  $(".ui-tooltip").remove()

  throb()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "checks.php",
    type : "post",
    data : {
      customer_id : customer_id,
      user_id : window.user_id,
      //page: 0,
      //perpage: history_pane_perpage,
    },
    dataType : "json",
    success : [ajax_result_handler, parse_checks],
    complete : unthrob,
    error : [empty_main, ajax_error_handler],
  })
}

function parse_checks(data) {
  $(window.customer_pane).css({
    display : "none"
  })

  $(window.items_pane).css({
    display : "none"
  })

  $(window.packages_pane).css({
    display : "none"
  })

  $(window.consignor_pane).css({
    display : "none"
  })

  $(window.home_pane).remove()
  delete window.home_pane

  $(window.history_pane).remove()
  delete window.history_pane

  $(window.emails_pane).remove()
  delete window.emails_pane

  $(window.account_pane).remove()
  delete window.account_pane

  $(window.checks_pane).remove()
  delete window.checks_pane

  window.checks_pane = create_checks_pane(data)

  $(window.checks_pane).appendTo(document.body)
}

function create_checks_pane(data) {
  var pane = $("<div id='checks_pane' />").css({
    padding : "10px"
  })

  if (data.checks && data.checks.length) {
    var table = $($("#check_table").html())

    $(table).append(Mustache.render($("#check_rows").html(), data.checks)).appendTo(pane)

    $(table).find(".delete_icon").click(function() {
      if ($(this).parents("tr:first").hasClass("voided"))
        unvoid_check($(this).parents("tr:first"))
      else
        void_check($(this).parents("tr:first"))
    })

    $(table).find("tr").each(function() {
      if ($(this).data("voided")) {
        $(this).addClass("voided").find("td[name=void] .delete_icon").html("<img src='/includes/graphics/delete_x16.png' />")

        $(this).find("td[name=cash] button").css("visibility", "hidden")
      }
    })

    $(table).find(".cash_icon").click(function() {
      if ($(this).parents("tr:first").hasClass("cashed"))
        uncash_check($(this).parents("tr:first"))
      else
        cash_check($(this).parents("tr:first"))
    })

    $(table).find("tr").each(function() {
      if ($(this).data("cashed")) {
        $(this).addClass("cashed").find("td[name=cash] .cash_icon").html("<img src='/includes/graphics/checkmark.png' />")

        $(this).find("td[name=void] button").css("visibility", "hidden")
      }
    })

    $(table).find("input[name=note]").keyup(function(event) {

      if (event.keyCode == 13)//Enter
      {
        note_check($(this).parents("tr"))
      } else if ($(this).val() !== $(this).data("original")) {
        $(this).css("backgroundColor", "#FFA")
      }
    })
  } else {
    $(pane).append("This customer has no checks")
  }

  return pane
}

function create_items_pane(data) {
  var pane = $("<div />").attr("id", "items_pane").css({
    padding : "10px"
  })

  if (data.invoices.length == 0) {
    $(pane).append($("<div />").html("No Outstanding Invoices").css({

    }))
  }

  var left = $("<div />").css({
    display : "inline-block"
  })

  if (data.customer.pay_and_hold_due_dates && data.customer.pay_and_hold_due_dates.length) {
    $(left).append(Mustache.render($("#ph_due_dates").html(), data.customer))
  }

  $(notes_box(data.customer)).css({
    width : "450px"
  }).appendTo(left).find("textarea, input").removeClass("customers").attr("readonly", true).css("background", "transparent")

  $(left).append("<br />").append(newinvoice(data))

  var cards = $("<div />").addClass("cards").css({
    display : "inline-block",
    verticalAlign : "top",
  })

  $(newcard()).attr("id", "newcard2").appendTo(cards)

  $(left).append(cards).appendTo(pane)

  var invoices = $("<ul />").addClass("invoices").addClass("roundbox").css({
    display : "inline-block",
    verticalAlign : "top",
  })

  $(invoices).tooltip({
    items : "li.invoice",
    content : "Double-click to edit this invoice.",
    position : {
      my : "left+15 bottom",
      at : "right bottom"
    },
  })

  for (x in data.invoices) {
    var invoice = $(Mustache.render(window.templates.invoice_row, data.invoices[x]))

    $(invoice).addClass("noprint").find("img.print").css({
      cursor : "pointer"
    }).click(function() {
      $("#iframe").attr("src", "single_invoice.php?print=1&invoice_number=" + encodeURIComponent($(this).parents("li.invoice").data("invoice_number")))
    })

    $(invoice).find("img.shipped").css({
      cursor : "pointer",
    }).click(function() {
      mark_invoice_shipped($(this).parents("li.invoice").data("invoice_number"))
    })
    if ("pay_and_hold_due_date" in data.invoices[x] && data.invoices[x].days_past_pay_and_hold_due_date > 0) {
      $(invoice).css({
        backgroundColor : "#EDA1A1",
      })
    } else if (!("pay_and_hold_due_date" in data.invoices[x]) && data.invoices[x].days_old >= 5) {
      $(invoice).css({
        backgroundColor : "#EDA1A1",
      })
    } else if (data.invoices[x].invoice_printed == 0) {
      $(invoice).css({
        backgroundColor : "#d6ecdf",
      })
    } else {
      $(invoice).css({

      })
    }

    $(invoices).append(invoice)
  }

  $(pane).append(invoices)

  $(item_table_head("Enter New Item", "")).appendTo(pane)
  var new_item = $(Mustache.render($("#item_new").html())).appendTo(pane)

  $(new_item).find("[name=fixed_price_sale]").change(function(event) {

    $("#new_ebay_id").val("")

    if ($("#fixed_price_sale").prop("checked")) {
      $("#new_ebay_id").show()

      if ($("#new_item [name=ebay_item_number]").val() == "")
        $("#new_item [name=ebay_item_number]").val(window.data.next_bins_number)
    } else {
      $("#new_ebay_id").hide()

      $("#new_item [name=ebay_item_number]").val("")
    }
  })

  $(new_item).find("[name=ebay_item_number]").focus(function() {
    $(this).autocomplete("search", "")
  }).autocomplete({
    source : window.ebay_item_number_options,
    minLength : 0,
    delay : 0,
  })

  $(new_item).find("[name=date]").datepicker({
    dateFormat : "yy-mm-dd"
  })

  $(new_item).find("[name=ebay_title]").autocomplete({
    source : "suggest.php?name=newitem",
    minLength : 1,
    open : function() {
      $("#new_item input[name=price]").val("")
      $("#new_item [name=shipping_notes]").val("")
    },
    select : function(event, ui) {
      /*$("#new_item input[name=price]")
       .val(ui.item.price)*/

      $("#new_item [name=shipping_notes]").val(ui.item.type)

      $("#new_item [name=ebay_item_number]").val(ui.item.sales_type)
    },
    focus : function(event, ui) {
      /*$("#new_item input[name=price]")
       .val(ui.item.price)*/

      $("#new_item [name=shipping_notes]").val(ui.item.type)

      $("#new_item [name=ebay_item_number]").val(ui.item.sales_type)
    },
  })

  if ("unpaid" in data && data.unpaid.length) {
    $(pane).append(lowest_lot_numbers(data.customer.lowest_lot_numbers)).append($(unselect_all()).attr("id", "unselect_all_button")).append($(edit_reminder_notes_button()).attr("id", "reminder_notes_button"))

    $(pane).append(items_count(data)).append($(create_items_table(data)).attr("id", "items_table"))

    if ($("img.delete_icon", pane).length) {
      $("#reminder_notes_button", pane).after($(delete_all()))
    }

    if ((window.data.customer.billing_address && window.data.customer.billing_address.billing_address.bill_state == "MO") || (window.data.customer.customer.address.ship_state == "MO")) {
      $("#unselect_all_button", pane).after(sales_tax_button(pane))
    }
  } else {
    $(pane).append($("<div />").attr("id", "no_outstanding_items").html("No Outstanding Items").css({
      /*position: "fixed",
       top: "50%",
       left: "50%",
       marginLeft: "-70px",*/
    })).append($(create_items_table(data)).attr("id", "items_table"))
  }

  $(pane).find("#newinvoice").tooltip({
    items : "#newinvoice",
    content : "Ctrl+Enter to create this invoice",
    position : {
      my : "right top+15",
      at : "right bottom",
      of : $(pane).find("#newinvoice")
    },
  })

  $(pane).find("div.card_container:first").tooltip({
    items : "div.card_container",
    content : "Ctrl+Enter to add this card",
    position : {
      my : "left+15 center",
      at : "right center",
      of : $(pane).find("div.card_container")
    }
  })

  $(pane).find("#new_item").tooltip({
    items : "#new_item",
    content : "Ctrl+Enter to add this item",
    position : {
      my : "left+15 top",
      at : "right bottom",
      of : $(pane).find("#new_item")
    }
  })

  $(pane).find("table.items").tooltip({
    items : "table.items",
    content : "<ul><li>Arrows + Space to select</li><li>Ctrl+A to select/deselect all</li><li>Tab/Shift+Tab to change group</li></ul>",
    position : {
      my : "center bottom",
      at : "center top-15"
    },
  })

  return pane
}

function unselect_all() {
  return $("<button />").css({
    display : "inline-block"
  }).html("Unselect All").click(function() {

    if ($(this).html() == "Unselect All") {
      $(this).html("Select All")
      $("table.items tr").removeClass("selected")
    } else {
      $(this).html("Unselect All")
      $("table.items tr").addClass("selected")
    }

  })
}

function delete_all() {
  return $("<button />").css({
    display : "inline-block"
  }).html("Delete All").click(function() {
    delete_all_items()
  })
}

function edit_reminder_notes_button() {
  return $("<button />").css({
    display : "inline-block"
  }).html("Reminder Notes").click(function() {
    edit_reminder_note(function() {
      refresh()
    })
  })
}

function sales_tax_button(pane) {
  var tax = sales_tax(pane)

  return $("<button />").attr("id", "sales_tax_button").css({
    display : "inline-block"
  }).html("Add Sales Tax ($" + tax + ")").click(function() {
    var tax = sales_tax()
    $("#new_item input[name=ebay_item_number]").val("salestax")
    $("#new_item input[name=ebay_title]").val("Missouri Sales Tax of 8.162%")
    $("#new_item input[name=price]").val(tax)
    create_item()
  })
}

//TODO: This is unused
function sales_tax(pane) {
  total = 0
  $("#items_table tr.selected", pane).each(function() {
    if ($(this).data("price") > 0)
      total += parseFloat($(this).data("price"))
  })
  //total += parseFloat($("#shipping_charged").val())

  total *= 100

  total *= 0.08162

  total = Math.round(total)

  total /= 100

  return total
}

function lowest_lot_numbers(lowest_lot_numbers) {
  if (lowest_lot_numbers && lowest_lot_numbers.length) {
    return $("<div class='roundbox' style='display: inline-block; padding: 10px;' />").css({
      marginLeft : "10px",
      fontSize : "10pt",
    }).append("LLN ").append(lowest_lot_numbers.join(" "))
  } else
    return ""
}

function items_count(data) {
  return $("<span />").css({
    marginLeft : "10px",
    fontSize : "12pt",
  }).html(data.unpaid.length + " item" + (data.unpaid.length > 1 ? "s" : ""))
}

function create_items_table(data) {
  if (data.unpaid.length) {
    var combine_types = $("#combine_types").html()
    var template = $("#item_row2").html()

    last_date = data.unpaid[0].date

    var total = 0
    var count = 0

    var table = $("<table />")
    //.addClass("roundbox")
    .addClass("items").css({
      width : "1000px",
      marginTop : "15px",
      marginBottom : "15px",
    })

    if ("html_message" in data) {
      $(table).html(data.html_message)
    }

    var rows = []

    for (x in data.unpaid) {
      if (last_date != data.unpaid[x].date) {
        $(table).append($("<tbody class='items' />").css({
          borderBottom : "1px dashed gray",
        }).tooltip({
          content : last_date + "<br /><sup>$</sup>" + total + "<br />" + rows.length + " items",
          items : "tbody",
          position : {
            my : "left middle",
            at : "right middle",
            collision : "none"
          },
        }).append(rows))

        rows = []
        total = 0
      }

      if ((data.unpaid[x].in_consignments > 0 ? data.unpaid[x].deletion_notes : true)) {
        data.unpaid[x].delete_icon = "<img class='delete_icon' src='/includes/graphics/delete_x16.png' />"
      }

      if (data.unpaid[x].reminder_notes) {
        data.unpaid[x].reminders = "<img src='/includes/graphics/note.png' title=\"" + data.unpaid[x].reminder_notes.replace("\"", "&quot;") + "\" />"
      }

      if (data.unpaid[x].invoice_number) {
        data.unpaid[x].checkmark = "<a target='_blank' href='single_invoice.php?invoice_number=" + data.unpaid[x].invoice_number + "'>" + "<img src='/includes/graphics/checkmark.png' title='Invoice #" + data.unpaid[x].invoice_number + "' /></a>"
      } else {
        data.unpaid[x].checkmark = ""
      }

      if (data.unpaid[x].quote_requested || data.unpaid[x].quote_id) {
        var info = ""

        if (data.unpaid[x].quote_requested)
          info += "Requested " + data.unpaid[x].quote_requested + "\n"

        if (data.unpaid[x].quote_ready)
          info += "Ready " + data.unpaid[x].quote_ready + "\n";

        if (data.unpaid[x].quote_approved)
          info += "Approved " + data.unpaid[x].quote_approved + "\n";

        if (data.unpaid[x].quote_id) {
          if (data.unpaid[x].quote_approved) {
            if (data.unpaid[x].quote_type == "send_email")
              data.unpaid[x].quote_info = "<img title='" + info + "' src='graphics/approved_a.png' />"
            else
              data.unpaid[x].quote_info = "<img title='" + info + "' src='graphics/approved_b.png' />"
          } else if (data.unpaid[x].quote_ready) {
            data.unpaid[x].quote_info = "<img title='" + info + "' src='graphics/ready.png' />"
          } else {
            data.unpaid[x].quote_info = "<img title='" + info + "' src='graphics/created.png' />"
          }

          data.unpaid[x].quote_info = "<a href='/shipping_quotes/?customer=" + data.unpaid[x].quote_id + "' target='_blank'>" + data.unpaid[x].quote_info + "</a>"
        } else {
          data.unpaid[x].quote_info = "<img title='" + info + "' src='graphics/requested.png' />"

          data.unpaid[x].quote_info = "<a href='/shipping_quotes/?customer=" + window.customer_id + "' target='_blank'>" + data.unpaid[x].quote_info + "</a>"
        }
      } else {
        data.unpaid[x].quote_info = "<span style='width: 40px'>&nbsp;</span>"
      }

      total += parseFloat(data.unpaid[x].price)

      var row = $(Mustache.render(template, data.unpaid[x])).attr("tabindex", "0")

      if (data.select_invoice) {
        if (data.unpaid[x].invoice_number == data.select_invoice) {
          $(row).addClass("selected")
        }
      } else {
        $(row).addClass("selected")
      }

      $(row).find(".combine_type").append(combine_types).val(data.unpaid[x].combine_type).attr("original_value", data.unpaid[x].combine_type)

      if (data.unpaid[x].invoice)
        $(row).data("invoice", data.unpaid[x].invoice)
      else
        $(row).data("invoice", false)

      $(row).find("img.delete_icon").css({
        cursor : "pointer",
      }).click(function(event) {
        delete_item($(event.target).parents("tr:first"))
      })
      combine_type = $(row).find("select.combine_type")

      $(combine_type).change(function(event) {
        $(this).css("visibility", "hidden")

        edit_item($(this).parents("tr:first").data("autonumber"), {
          combine_type : $(this).val()
        }, function(item) {
          $(this).css("visibility", "visible").attr("original_value", item.combine_type).val(item.combine_type)
        }.bind(this), function(item) {
          $(this).css("visibility", "visible").val($(this).attr("original_value"))
        }.bind(this))
      })
      if (data.unpaid[x].deletion_notes) {
        $(row).tooltip({
          content : "Click to delete<br />" + data.unpaid[x].deletion_notes,
          items : "img.delete_icon",
          position : {
            collision : "none"
          },
        })
      } else {
        $(row).tooltip({
          content : "Click to delete",
          items : "img.delete_icon",
          position : {
            collision : "none"
          },
        })
      }

      rows.push(row)

      last_date = data.unpaid[x].date
    }

    $(table).append($("<tbody class='items' />").css({
      borderBottom : "1px dashed gray",
    }).tooltip({
      content : last_date + "<br /><sup>$</sup>" + total + "<br />" + rows.length + " items",
      items : "tbody",
      position : {
        my : "left middle",
        at : "right middle",
        collision : "none"
      }
    }).append(rows))

    rows = []
    total = 0

    return table
  } else {

    return $("<table />")
    //.addClass("roundbox")
    .addClass("items").css({
      width : "1000px",
      marginTop : "15px",
      marginBottom : "15px",
    })
  }
}

/*
 Package pane stuff
 */

function clear_package_search() {
  $("#packages li").removeClass("selected").removeClass("hidden")

  $("#package_contents li").removeClass("selected")

  deuglify_tracking_numbers()
}

function package_search(search) {
  show_more_packages()
  clear_package_search()

  throb()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "packages.php",
    dataType : "json",
    type : "post",
    data : {
      customer_id : window.customer_id,
      item_search : search,
      user_id : window.user_id,
    },
    dataType : "json",
    success : [ajax_result_handler, parse_package_search],
    complete : unthrob,
    error : [empty_main, ajax_error_handler],
  })
}

function parse_package_search(data) {
  if (data.result) {
    if (data.result.length) {
      window.package_search_autonumbers = []

      for (x in data.result) {
        if (data.result[x].autonumber)
          window.package_search_autonumbers.push(data.result[x].autonumber)

        if (data.result[x].tracking_id) {
          $("#packages li#tracking" + data.result[x].tracking_id).addClass("selected")
        } else if (data.result[x].invoice_number) {
          $("#packages li#invoice" + data.result[x].invoice_number).addClass("selected")
        }
      }

      $("#packages li:not(.selected)").addClass("hidden")

      if (data.result[0].tracking_id)
        get_package_contents(data.result[0].tracking_id)
      else if (data.result[0].invoice_number)
        get_invoice_contents(data.result[0].invoice_number)

      clear_status()
    } else {
      status("<img src='/includes/graphics/alert16.png' /> No results searching packages.")
      $("#packages li").addClass("hidden")
      $("#package_contents").empty()
    }
  } else {
    status("<img src='/includes/graphics/alert16.png' /> No results searching packages.")
    $("#packages li").addClass("hidden")
    $("#package_contents").empty()
  }

  deuglify_tracking_numbers()
}

function show_more_packages() {
  var packages = window.data.packages[0]
  var ids = []

  $("#packages li").each(function() {
    ids.push($(this).attr("tracking_id"))
  })
  for (x in packages) {
    if (ids.indexOf(packages[x].ID) !== -1)
      continue;

    $("#packages").append(Mustache.render($("#package_row").html(), packages[x]))
  }

  $("#load_more_packages").remove()
}

function generate_package_ul() {
  return $("<ul />").mouseover(function(event) {
    if ($(event.target).is("li.package")) {
      get_package_contents($(event.target).attr("tracking_id"))
      select_package(event.target)
    } else if ($(event.target).parents("li.package").length) {
      get_package_contents($(event.target).parents("li:first").attr("tracking_id"))
      select_package($(event.target).parents("li:first"))
    } else if ($(event.target).is("li.untracked_invoice")) {
      get_invoice_contents($(event.target).attr("invoice_number"))
      select_package(event.target)
    } else if ($(event.target).parents("li.untracked_invoice").length) {
      get_invoice_contents($(event.target).parents("li:first").attr("invoice_number"))
      select_package($(event.target).parents("li:first"))
    }
  })
}

function select_package(element) {
  $("#packages li.selected").removeClass("selected")
  $(element).addClass("selected")
}

function subinvoice_row(invoices) {
  var li = $("<li />").addClass("subinvoice")
  //.html("<big>&#8627;</big> ")
  .html("<big>&#10551;</big> ")

  for (x in invoices) {
    $(li).append($("<a />").data("invoice_number", invoices[x]).click(function() {
      focus_tab($("#items_tab"))
      get_invoice($(this).data("invoice_number"))
    }).text(invoices[x]))
  }

  return li
}

function create_packages_pane(packages) {
  var pane = $("<div />").attr("id", "packages_pane").css({
    padding : "10px",
    position : "relative",
  })

  if (packages[0] || packages[2]) {
    $("<div />").css({
      marginLeft : "10px"
    }).append($("<input placeholder='search for an item' />").attr("id", "package_search")
    /*.tooltip({
     content: "<ul><li>Enter to search item title</li><li>Esc to clear search</li></ul>",
     items: "input",
     position: {my: "right top", at: "right bottom"}
     })*/.css({
      width : "550px"
    }).keyup(function(event) {
      if (event.keyCode == 13) {
        package_search($(this).val().trim())
      } else if (event.keyCode == 27 || $(this).val().trim() == "") {
        clear_package_search()
        $(this).val("")
      }
    })).appendTo(pane)

    $($("#packages_header").html()).appendTo(pane)

    var div = $("<div />").attr("id", "packages").addClass("roundbox").css({
      width : "680px",
      height : "600px",
      overflow : "auto",
      display : "inline-block",
    })

    //var limit = 30
    var x = 0

    var list = []

    for (id in packages[0]) {
      /*
       Add in the untracked invoices. Keep in date order with the packages.
       */
      while (packages[2][x] && packages[2][x].date_of_invoice > packages[0][id].ship_date) {
        list.push($(Mustache.render($("#untracked_invoice_row").html(), packages[2][x])))

        x++;
      }

      list.push($(Mustache.render($("#package_row").html(), packages[0][id])))
    }

    /*
     Add remaining untracked invoices.
     */
    while (packages[2][x]) {
      list.push($(Mustache.render($("#untracked_invoice_row").html(), packages[2][x])))

      x++;
    }

    var last_invoice_num = $(list[0]).data("invoice_num")
    var ul = generate_package_ul(last_invoice_num)

    for (x in list) {
      if ($(list[x]).data("invoice_num") != last_invoice_num) {
        if (packages[3] && packages[3][last_invoice_num]) {
          $(ul).append(subinvoice_row(packages[3][last_invoice_num]))
        }

        $(div).append(ul)

        var ul = $(generate_package_ul()).data("invoice_num", $(list[x]).data("invoice_num"))
      }

      $(ul).append($(list[x]))

      last_invoice_num = $(list[x]).data("invoice_num")
    }

    if (packages[3] && packages[3][last_invoice_num]) {
      $(ul).append(subinvoice_row(packages[3][last_invoice_num]))
    }

    $(div).append(ul).appendTo(pane)
  } else {
    $(pane).append($("<div />").html("No Packages").css({
      position : "fixed",
      top : "50%",
      left : "50%",
      marginLeft : "-70px",
    }))
  }

  $("<ul />").addClass("roundbox").attr("id", "package_contents").css({
    width : "670px",
    height : "530px",
    display : "inline-block",
    position : "absolute",
    left : "700px",
    top : "129px",
  }).appendTo(pane)

  return pane
}

function get_invoice_contents(invoice_number) {
  if (!callback) {
    var callback = function() {
    }
  }

  if ($("#package_contents").attr("tracking_id") == "i" + invoice_number)
    return true;

  $("#package_contents").empty().attr("tracking_id", "i" + invoice_number)

  $("#extra_info").remove()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "packages.php",
    type : "post",
    dataType : "json",
    data : {
      request : "contents",
      invoice_number : invoice_number,
      user_id : window.user_id,
    },
    success : [ajax_result_handler,
    function(data) {
      parse_package_contents(data)
    }],
    error : ajax_error_handler,
  })
}

function get_package_contents(tracking_id) {
  if (!callback) {
    var callback = function() {
    }
  }

  if ($("#package_contents").attr("tracking_id") == tracking_id)
    return true;

  $("#package_contents").empty().attr("tracking_id", tracking_id)

  $("#extra_info").remove()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "packages.php",
    type : "post",
    dataType : "json",
    data : {
      request : "contents",
      tracking_id : tracking_id,
      user_id : window.user_id,
    },
    success : [ajax_result_handler,
    function(data) {
      parse_package_contents(data)
    }],
    error : ajax_error_handler,
  })
}

function parse_package_contents(data) {
  //TODO: show what phone orders shows (invoice details)

  var template = $("#item_row").html()

  ul = $("#package_contents")

  if ("html_message" in data) {
    $(ul).html(data.html_message)
  }

  if ("contents" in data) {
    $(ul).html(Mustache.render(template, data))

    for (x in data.contents) {
      if (data.contents[x].invoiced_to_bruce) {
        $("#item" + data.contents[x].autonumber).find("small").attr("title", "Invoiced to Bruce").css({
          textDecoration : "line-through"
        })

      }
    }
  }

  if (window.package_search_autonumbers) {
    for (x in window.package_search_autonumbers) {
      $("#item" + window.package_search_autonumbers[x]).addClass("selected")
    }
  }

  if ("other_info" in data) {
    $(Mustache.render($("#invoice_info").html(), data)).attr("id", "extra_info").css({
      width : "670px",
      height : "100px",
      display : "inline-block",
      position : "absolute",
      left : "700px",
      top : "5px",
    }).appendTo("#packages_pane")
  }

  //$("#packages")
  //	.after(ul)
}

/*
 Customer pane stuff
 */

function parse_customer(data) {
  window.expected_hash = jQuery.param({
    customer_id : data.customer.customer.address.customer_id,
    tab : $("#tabs .focused").attr("id")
  })
  window.location.hash = window.expected_hash

  $("#main_menu").hide()

  if (data.customer.customer.address.vip == 1) {
    $(document.body).css({
      backgroundImage : "url('graphics/coin.png')",
    })
  } else {
    $(document.body).css({
      backgroundImage : ""
    })
  }

  $(window.customer_pane).remove()
  $(window.items_pane).remove()
  $(window.packages_pane).remove()
  $(window.consignor_pane).remove()

  window.address = data.customer.customer

  window.data = data

  window.customers_id = data.customer.customer.address.customers_id
  window.customer_id = data.customer.customer.address.customer_id
  window.customer_email = data.customer.customer.address.email

  $("#customer_id").html(data.customer.customer.address.customer_id).attr("href", "?customer_id=" + data.customer.customer.address.customer_id)

  if (data.customer.alerts && data.customer.alerts.length) {
    for (x in data.customer.alerts) {
      $("#root").prepend(alert_box(data.customer.alerts[x]))
    }
  }

  $("#vip").prop("checked", window.data.customer.customer.address.vip != "0")

  $("#frequent_buyer").prop("checked", window.data.customer.customer.address.frequent_buyer != "0")

  $("#tax_exempt").prop("checked", window.data.customer.customer.address.tax_exempt != "0")

  if (data.new_customer) {
    document.title = "New Customer"

    $("#quote_request, #refresh, #clip").css({
      visibility : "hidden",
    })

    $("#mailto").css({
      visibility : "hidden",
    }).unbind("click")

    /*
     External links
     */
    $("#shipping_quotes").attr("href", "/shipping_quotes/").text("Shipping Quotes")

    $("#bidder_account_admin").attr("href", "/website_tools/user_account_admin.php").text("Bidder Account Admin")

    $("#menu_button").css({
      visibility : "hidden"
    })
  } else {
    document.title = data.customer.customer.address.name + " (" + data.customer.customer.address.customer_id + ")"

    $("#refresh, #quote_request").css({
      visibility : "",
    })

    $("#customer_dump").unbind("click").click(function() {
      $("#iframe").attr("src", "customer_dump.php?email=" + encodeURIComponent(data.customer.customer.address.email) + "&customer_id=" + encodeURIComponent(data.customer.customer.address.customer_id))
    })

    $("#mailto").css({
      visibility : "",
    }).unbind("click").click(function() {
      $("#iframe").attr("src", "mailto: " + data.customer.customer.address.name + " <" + data.customer.customer.address.email + ">")
    })
    /*
     External links
     */
    $("#shipping_quotes").attr("href", "/shipping_quotes/?customer=" + encodeURIComponent(data.customer.customer.address.email)).text("Shipping Quotes (" + data.customer.customer.address.customer_id + ")")

    $("#bidder_account_admin").attr("href", "/website_tools/user_account_admin.php?user=" + encodeURIComponent(data.customer.customer.address.email)).text("Bidder Account Admin (" + data.customer.customer.address.customer_id + ")")

    $("#menu_button").css({
      visibility : ""
    })

    update_clip(window.data.customer)

  }

  if (data.customer.aa_accounts.length) {
    $("#auction_anything").attr("href", "http://auctions.emovieposter.com/AdminMemberProfile.taf?_function=detail&id=" + encodeURIComponent(data.customer.customer.address.AA_ID)).text("Auction Anything Profile (" + data.customer.customer.address.username + ")").show()
  } else {
    $("#auction_anything").attr("href", "#").text("Auction Anything Profile").hide()
  }

  /*
   More than one AA username
   */
  if (data.customer.aa_active_accounts && data.customer.aa_active_accounts.length > 1) {
    var links = ""

    for (x in data.customer.aa_active_accounts) {
      links += "<a target='_blank' href='http://auctions.emovieposter.com/AdminMemberProfile.taf?_function=detail&id=" + data.customer.aa_active_accounts[x].ID + "'>" + data.customer.aa_active_accounts[x].Username + "</a> "
    }

    $("#root").prepend(alert_box("<b style='font-size: 120%; color: red'>This customer has more than one active Auction Anything account: " + links + "</b>"))
  }

  /*
   Customers with similar names
   */
  if (data.customer.similar_names && data.customer.similar_names.length) {
    $("#root").prepend(alert_box("There are other customers with similar names."))
  }

  /*
   Shared Accounts
   */
  if (data.customer.shared_accounts && data.customer.shared_accounts.length) {
    $("#root").prepend(alert_box("This customer has multiple accounts."))
  }

  /*
   Create panes
   */
  window.customer_pane = create_customer_pane(data)

  window.items_pane = create_items_pane(data)

  //window.packages_pane = create_packages_pane(data.packages)

  window.consignor_pane = create_consignor_pane(data)

  $(window.customer_pane).css({
    display : "none"
  }).appendTo(document.body)

  $(window.items_pane).css({
    display : "none"
  }).appendTo(document.body)

  $(window.packages_pane).css({
    display : "none"
  }).appendTo(document.body)

  $(window.consignor_pane).css({
    display : "none"
  }).appendTo(document.body)

  add_autocompletes(window.customer_pane)

  if (data.customer.consignor && data.customer.consignor.length) {
    $("#consignor_tab a").text("Consignor")
  } else {
    $("#consignor_tab a").text("New Consignor")
  }

  $("#customer_tab, #items_tab, #packages_tab, #history_tab, #consignor_tab, #email_tab, #account_tab, #checks_tab").css({
    visibility : "visible"
  })

  snapshot_inputs()
}

function show_customer_pane() {
  $(".ui-tooltip").remove()

  $.ajaxq.abort("fetch")

  $(window.items_pane).css({
    display : "none"
  })

  $(window.packages_pane).css({
    display : "none"
  })

  $(window.customer_pane).css({
    display : ""
  })

  $(window.consignor_pane).css({
    display : "none"
  })

  $(window.history_pane).remove()
  delete window.history_pane

  $(window.emails_pane).remove()
  delete window.emails_pane

  $(window.home_pane).remove()
  delete window.home_pane

  $(window.account_pane).remove()
  delete window.account_pane

  $(window.checks_pane).remove()
  delete window.checks_pane

  /*
   Add More/Less to the Paypal Payments
   */
  /*
   $(window.customer_pane)
   .find("#paypal_payments table")
   .readmore({
   moreLink: "<a href='#'>More</a>",
   lessLink: "<a href='#'>Less</a>",
   maxHeight: 90,
   })
   */

  /*
   Add More/Less to the block notes
   */
  $(window.customer_pane).find("#block_records div.block_notes").readmore({
    moreLink : "<a href='#'>More</a>",
    lessLink : "<a href='#'>Less</a>",
    maxHeight : 50,
  })

  $("[name=email]").focus()

  update_customer_pane_total()

  $("[name=notes_for_invoice]").autoHeight()
}

/*
 Home pane stuff
 */

function show_home_pane(data) {
  $(".ui-tooltip").remove()

  $(window.customer_pane).css({
    display : "none"
  })

  $(window.items_pane).css({
    display : "none"
  })

  $(window.packages_pane).css({
    display : "none"
  })

  $(window.consignor_pane).css({
    display : "none"
  })

  $(window.history_pane).remove()
  delete window.history_pane

  $(window.emails_pane).remove()
  delete window.emails_pane

  $(window.home_pane).remove()
  delete window.home_pane

  $(window.account_pane).remove()
  delete window.account_pane

  $(window.checks_pane).remove()
  delete window.checks_pane

  throb()

  $.ajaxq.abort("fetch")

  close()

  $.ajaxq("fetch", {
    url : "home.php",
    type : "post",
    data : {
      user_id : window.user_id,
      user_name : window.user_name,
    },
    dataType : "json",
    success : [ajax_result_handler, parse_home_pane,
    function() {
      $("#search").focus()
      $("#customer_tab, #items_tab, #packages_tab, #history_tab, #consignor_tab, #email_tab, #account_tab, #checks_tab").css({
        visibility : "hidden"
      })
    }],
    complete : unthrob,
    //error: [empty_main, ajax_error_handler],
  })
}

function paypal_search(term) {
  throb()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "home.php",
    type : "post",
    data : {
      user_id : window.user_id,
      search : term,
    },
    dataType : "json",
    success : [ajax_result_handler, parse_paypal_search],
    complete : unthrob,
    //error: [empty_main, ajax_error_handler],
  })
}

function parse_paypal_search(data) {
  if (data.payments) {
    $("#home_paypal_table tr.pp_payment").remove()

    for (x in data.payments) {
      $("#home_paypal_table").append(create_payment_row(data.payments[x]))
    }
  }
}

function get_unprocessed_orders(data) {
  var ret = []
  for (var i = 0; i < data.length; i++) {
    if (data[i].op != 'quote_request') {
      ret.push(data[i])
    }
  }
  return ret
}

function get_unprocessed_quotes(data) {
  var ret = []
  for (var i = 0; i < data.length; i++) {
    if (data[i].op == 'quote_request') {
      ret.push(data[i])
    }
  }
  return ret
}

function parse_home_pane(data) {
  window.home_pane = $("<div />").css({
    padding : "10px"
  }).appendTo(document.body)

  if (data.payments && data.payments.length) {
    $(Mustache.render($("#paypal_payment_search").html())).appendTo(window.home_pane)

    $(window.home_pane).find("input:first").keyup(function(event) {

      if (event.keyCode == 13) {
        paypal_search($(event.target).val())
      } else if (event.keyCode == 27) {
        $(this).val("")
        paypal_search("")
      }
    }).tooltip({
      content : "Enter to search; Esc to clear",
      items : "input",
    })

    for (x in data.payments) {
      $("#home_paypal_table").append(create_payment_row(data.payments[x]))
    }

  }

  if (data.invoices) {
    var invoices = $("<div />").attr("id", "unprinted_invoices").css({
      width : "1000px",
      marginRight : "auto",
      marginLeft : "auto",
    })

    var count = 0

    for (who in data.invoices) {
      count++

      if (who == "")
        var name = "(unknown)"
      else
        var name = who

      var element = $(Mustache.render($("#unprinted_invoices").html(), {
        total : data.invoices[who].length,
        invoices : data.invoices[who],
        who : name
      }))

      $(element).find(".heading").css({
        cursor : "pointer",
      }).click(function() {

        this.toggled = !this.toggled

        $(this).parents("div:first").find("tr input").prop("checked", this.toggled)
      })

      $(element).appendTo(invoices)
    }

    if (count) {
      $(invoices).append($("<button />").css({
        margin : "20px",
        padding : "5px",
      }).html("<img src='/includes/graphics/printer.png' /> Print Selected Invoices").click(function() {
        print_selected_invoices()
      }))

      $(invoices).appendTo(window.home_pane)
    }
  }

  if (data.autoships && data.autoships.length) {
    $(Mustache.render($("#autoships").html(), data.autoships)).appendTo(window.home_pane)

  }

  if (data.orders && data.orders.length) {
    var div = $("<div />").attr("id", "unprocessed_orders").addClass("orders").css({
      width : "1000px",
      marginRight : "auto",
      marginLeft : "auto",
    })

    if (data.orders && data.orders.length) {
      if (get_unprocessed_orders(data.orders).length) {
        $("<button />").css({
          margin : "20px",
          padding : "5px",
        }).html("<img src='/includes/graphics/invoice.png' /> Process " + get_unprocessed_orders(data.orders).length + " Payment" + (get_unprocessed_orders(data.orders).length != 1 ? "s" : "")).click(function() {
          process_orders({
            orders_only : true
          })
        }).appendTo(div)
      }

      if (get_unprocessed_quotes(data.orders).length) {
        $("<button />").css({
          margin : "20px",
          padding : "5px",
        }).html("<img src='/includes/graphics/invoice.png' /> Process " + get_unprocessed_quotes(data.orders).length + " Quote" + (get_unprocessed_quotes(data.orders).length != 1 ? "s" : "")).click(function() {
          process_orders({
            quotes_only : true
          })
        }).appendTo(div)
      }

      $(Mustache.render($("#unprocessed_order").html(), data.orders)).appendTo(div)
    }

    $(div).find("tr").each(function() {

      var td = $(this).find("td.pending")

      if ($(td).html() != "") {
        $("<label>Process <input type='checkbox' class='process_order' name='process" + $(this).data("id") + "' value='1' /></label>").css({
          display : "block"
        }).appendTo(td)
      }
    })

    $(div).appendTo(window.home_pane)
  }

  if (data.processed && data.processed.length) {
    var div = $("<div />").addClass("orders").css({
      width : "1000px",
      marginRight : "auto",
      marginLeft : "auto",
    })

    $("<h4 />").text(data.processed.length + " orders processed today").appendTo(div)

    $(Mustache.render($("#unprocessed_order").html(), data.processed)).appendTo(div)

    $(div).appendTo(window.home_pane)
  }

  if (data.quote_requests && data.quote_requests.length) {
    var div = $("<div />").addClass("orders").css({
      width : "1000px",
      height : "500px",
      overflow : "auto",
      marginRight : "auto",
      marginLeft : "auto",
    })

    $("<h4 />").text(data.quote_requests.length + " quote requests").appendTo(div)

    $(Mustache.render(window.templates.quote_requests_all, data.quote_requests)).appendTo(div)

    $(div).appendTo(window.home_pane)
  }

  if (data.debtors && data.debtors.length) {
    var div = $("<div />").addClass("orders").css({
      width : "1000px",
      marginRight : "auto",
      marginLeft : "auto",
    })

    $("<h4 />").text("Bruce's Most Wanted").appendTo(div)

    $(Mustache.render($("#debtors").html(), data.debtors)).appendTo(div)

    $(div).appendTo(window.home_pane)
  }

  if (data.subscriptions) {
    var div = $("<div />").addClass("orders").css({
      width : "1000px",
      marginRight : "auto",
      marginLeft : "auto",
    })

    $("<h4 />").text("Your Subscriptions").append(" <img src='/includes/graphics/help.png' class='hoverbutton' onclick='help2(\"Subscriptions\")' style='vertical-align: middle' />").appendTo(div)

    $(Mustache.render(window.templates.subscriptions, data.subscriptions)).appendTo(div)

    $(div).appendTo(window.home_pane)
  }
}

/*
 History pane stuff
 */

function show_history_pane() {
  $(".ui-tooltip").remove()

  throb()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "history.php",
    type : "post",
    data : {
      customer_id : customer_id,
      user_id : window.user_id,
      page : 0,
      perpage : history_pane_perpage,
    },
    dataType : "json",
    success : [ajax_result_handler, parse_history],
    complete : unthrob,
    error : [empty_main, ajax_error_handler],
  })
}

function load_more_history(page) {
  $(".ui-tooltip").remove()

  throb()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "history.php",
    type : "post",
    data : {
      customer_id : customer_id,
      user_id : window.user_id,
      page : page,
      perpage : history_pane_perpage,
    },
    dataType : "json",
    success : [ajax_result_handler, parse_more_history],
    complete : unthrob,
    error : [empty_main, ajax_error_handler],
  })
}

function parse_more_history(data) {
  var pane = $("#history_pane")

  if (data.items && data.items.length) {
    var template = $("#item_row2").html()

    last_date = data.items[0].date

    total = 0;

    table = $("<table />")
    //.addClass("roundbox")
    .addClass("items").css({
      width : "1000px",
      marginTop : "0px",
      marginBottom : "0px",
    })

    if ("html_message" in data) {
      $(table).html(data.html_message)
    }

    for (x in data.items) {
      if (last_date != data.items[x].date) {
        $(pane).append(item_table_head(last_date, total.toFixed(2))).append(table)

        table = $("<table />").addClass("items")
        //.addClass("roundbox")
        .css({
          width : "1000px",
          marginTop : "0px",
          marginBottom : "0px",
        })

        total = 0
      }

      total += parseFloat(data.items[x].price)

      var row = $(Mustache.render(template, data.items[x])).attr("tabindex", "0")
      //.addClass("selected")

      if (!data.items[x].invoice_number) {
        $(row).addClass("selected")
      }

      $(table).append(row)

      last_date = data.items[x].date
    }

    $(pane).append(item_table_head(data.items[x].date, total.toFixed(2))).append(table)

    $(pane).append(table)

    if (data.items.length >= history_pane_perpage) {
      $("<div />").css({
        textAlign : "center",
        margin : "15px 0px 15px 0px",
      }).append($("<button>Load More</button>").click(function() {
        load_more_history(parseInt(data.page) + 1)
        $(this).parents("div:first").remove()
      })).appendTo(pane).get(0).scrollIntoView()
    } else {
      $("<div />").css({
        margin : "15px 0px 15px 500px",
      }).text("No More Items").appendTo(pane).get(0).scrollIntoView()
    }
  } else {
    $("<div />").css({
      margin : "15px 0px 15px 500px",
    }).text("No More Items").appendTo(pane).get(0).scrollIntoView()
  }

  window.history_pane_page = data.page

  return pane
}

function parse_history(data) {
  $(window.customer_pane).css({
    display : "none"
  })

  $(window.items_pane).css({
    display : "none"
  })

  $(window.packages_pane).css({
    display : "none"
  })

  $(window.consignor_pane).css({
    display : "none"
  })

  $(window.home_pane).remove()
  delete window.home_pane

  $(window.history_pane).remove()
  delete window.history_pane

  $(window.emails_pane).remove()
  delete window.emails_pane

  $(window.account_pane).remove()
  delete window.account_pane

  window.history_pane = create_history_pane(data)

  $(window.history_pane).appendTo(document.body)
}

function create_history_pane(data) {
  var pane = $("<div />").attr("id", "history_pane").css({
    padding : "10px"
  })

  if (data.credits && data.credits.length) {
    var template = window.templates.credit_row

    table = $("<table />").addClass("credits").addClass("items").addClass("history").css({
      marginTop : "0px",
      marginBottom : "0px",
      fontSize : "12px",
    })

    for (x in data.credits) {
      var row = $(Mustache.render(template, data.credits[x])).attr("tabindex", "0")
      //.addClass("selected")

      $(table).append(row)
    }

    $(pane).append(item_table_head("Credits", "")).append(table)
  }

  if (data.items && data.items.length) {
    var template = window.templates.item_row2

    last_date = data.items[0].date

    total = 0;

    table = $("<table />")
    //.addClass("roundbox")
    .addClass("items").addClass("history").css({
      width : "100%",
      marginTop : "0px",
      marginBottom : "0px",
    })

    if ("html_message" in data) {
      $(table).html(data.html_message)
    }

    for (x in data.items) {
      if (last_date != data.items[x].date) {
        $(pane).append(item_table_head(last_date, total.toFixed(2))).append(table)

        table = $("<table />").addClass("items").addClass("history")
        //.addClass("roundbox")
        .css({
          width : "100%",
          marginTop : "0px",
          marginBottom : "0px",
        })

        total = 0
      }

      total += parseFloat(data.items[x].price)

      var row = $(Mustache.render(template, data.items[x])).attr("tabindex", "0")
      //.addClass("selected")

      if (!data.items[x].invoice_number) {
        $(row).addClass("selected")
      }

      $(table).append(row)

      last_date = data.items[x].date
    }

    $(pane).append(item_table_head(data.items[x].date, total.toFixed(2))).append(table)

    $(pane).append(table)

    if (data.items.length == history_pane_perpage) {
      $("<div />").css({
        margin : "15px 0px 15px 0px",
        padding : "0px 0px 0px 500px",
      }).append($("<button>Load More</button>").click(function() {
        load_more_history(1)
        $(this).parents("div:first").remove()
      })).appendTo(pane)
    }
  } else {
    $(pane).append("No Items")
  }

  window.history_pane_page = 0

  return pane
}

function process_orders(options) {
  throb()

  var queryString = ""

  if ( typeof options.quotes_only != "undefined" && options.quotes_only) {
    queryString = "?quotes_only=1"
  }

  if ( typeof options.orders_only != "undefined" && options.orders_only) {
    queryString = "?orders_only=1"
  }

  $.ajaxq.abort("fetch")

  data = {
    user_id : window.user_id,
  }

  $("#unprocessed_orders input.process_order").each(function() {
    if ($(this).prop("checked"))
      data[$(this).attr("name")] = 1
  })

  $.ajaxq("fetch", {
    url : "process_cart_orders.php" + queryString,
    dataType : "json",
    type : "post",
    data : data,
    dataType : "json",
    success : [ajax_result_handler,
    function(data) {

      //Terri said she does not like this summary dialog
      //so I removed it. AK 2016-02-22
      //Put it back because of new checkout 2016-09-26
      dialog = $("<div />").css({
        overflow : "auto",
        whiteSpace : "pre-wrap",
        fontSize : "10pt",

      }).text(data.output).dialog({
        title : "Processed Orders",
        width : 500,
        height : 300,
        modal : true,
        close : function(event, ui) {
          focus_tab($("#home_tab"))
        }
      })

      focus_tab($("#home_tab"))

    }],
    complete : unthrob,
    error : [ajax_error_handler],
  })
}

function void_check(row) {
  throb()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "/accounting/update.php",
    type : "post",
    data : {
      void_check : $(row).data("check_id"),
      user_id : window.user_id,
    },
    dataType : "json",
    success : [
    function(data) {
      if ("success" in data) {
        $(row).addClass("voided").find("td[name=void] .delete_icon").html("<img src='/includes/graphics/delete_x16.png' />")

        $(row).find("td[name=cash] button").css("visibility", "hidden")
      }
    }, ajax_result_handler],
    complete : unthrob,
    error : [ajax_error_handler],
  })
}

function note_check(row) {
  throb()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "/accounting/update.php",
    type : "post",
    data : {
      note_check : $(row).data("check_id"),
      note : $(row).find("input[name=note]").val(),
      user_id : window.user_id,
    },
    dataType : "json",
    success : [
    function(data) {
      if ("success" in data) {
        note = $(row).find("input[name=note]")

        $(note).css("backgroundColor", "#AFA").data("original", $(note).val())

      }
    }, ajax_result_handler],
    complete : unthrob,
    error : [ajax_error_handler],
  })
}

function unvoid_check(row) {
  throb()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "/accounting/update.php",
    type : "post",
    data : {
      unvoid_check : $(row).data("check_id"),
      user_id : window.user_id,
    },
    dataType : "json",
    success : [
    function(data) {
      if ("success" in data) {
        $(row).removeClass("voided").find("td[name=void] .delete_icon").html("<img src='/includes/graphics/blank16.png' />")

        $(row).find("td[name=cash] button").css("visibility", "visible")
      }
    }, ajax_result_handler],
    complete : unthrob,
    error : [ajax_error_handler],
  })
}

function cash_check(row) {
  throb()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "/accounting/update.php",
    type : "post",
    data : {
      cash_check : $(row).data("check_id"),
      user_id : window.user_id,
    },
    dataType : "json",
    success : [
    function(data) {
      if ("success" in data) {
        $(row).addClass("cashed").find("td[name=cash] .cash_icon").html("<img src='/includes/graphics/checkmark.png' />")

        $(row).find("td[name=void] button").css("visibility", "hidden")
      }
    }, ajax_result_handler],
    complete : unthrob,
    error : [ajax_error_handler],
  })
}

function uncash_check(row) {
  throb()

  $.ajaxq.abort("fetch")

  $.ajaxq("fetch", {
    url : "/accounting/update.php",
    type : "post",
    data : {
      uncash_check : $(row).data("check_id"),
      user_id : window.user_id,
    },
    dataType : "json",
    success : [
    function(data) {
      if ("success" in data) {
        $(row).removeClass("cashed").find("td[name=cash] .cash_icon").html("<img src='/includes/graphics/blank16.png' />")

        $(row).find("td[name=void] button").css("visibility", "visible")
      }
    }, ajax_result_handler],
    complete : unthrob,
    error : [ajax_error_handler],
  })
}

function user_action_log() {
  throb()

  $.ajax({
    url : "user_action_log.php",
    dataType : "json",
    data : {
      emails : [window.data.customer.aa_active_accounts[0].email]
    },
    success : function(data) {
      if ("result" in data) {
        console.debug(window.data.customer.aa_active_accounts[0].email)
        console.debug(data.result[window.data.customer.aa_active_accounts[0].email])

        var w = window.open()

        w.document.open()

        w.document.write(Mustache.render(window.templates.user_action_log, data.result[window.data.customer.aa_active_accounts[0].email]))

        w.document.close()

        w.document.title = "User action log for " + window.data.customer.aa_active_accounts[0].Username
      }
    },
    complete : [unthrob],
    error : [ajax_error_handler],
  })
}

function update_clip(customer) {
  if (window.ffclipboard) {
    $("#clip").css({
      visibility : "",
    }).unbind("click").click(function() {
      var index = $("#aTabs div.ui-tabs-panel:visible").index()

      if (index == 1 || index == $("#aTabs div.ui-tabs-panel").length)
        var text = customer.customer.formatted.replace(/\r/g, "").replace(/\n/g, "\r\n")
      else
        var text = customer.other_addresses[index - 2].formatted.replace(/\r/g, "").replace(/\n/g, "\r\n")

      window.ffclipboard.setText(text);
    })
  } else {
    $("#clip").css({
      visibility : "",
    }).unbind("click").click(function() {
      var index = $("#aTabs div.ui-tabs-panel:visible").index()

      if (index == 1 || index == $("#aTabs div.ui-tabs-panel").length || index == -1)
        var text = customer.customer.formatted.replace(/\r/g, "").replace(/\n/g, "\r\n")
      else
        var text = customer.other_addresses[index - 2].formatted.replace(/\r/g, "").replace(/\n/g, "\r\n")

      textarea = $("<textarea readonly='readonly' />").css({
        width : "250px",
        height : "100px",
      }).val(text)

      $("<div />").append(textarea).dialog({
        title : "Ctrl+C to copy"
      })

      textarea.get(0).selectionStart = 0
      textarea.get(0).selectionEnd = text.length

    })
  }
}

function subscribe(subscribe) {
  $.ajaxq("main_menu", {
    url : "update.php",
    type : "post",
    data : {
      customers_id : window.customers_id,
      user_id : window.user_id,
      subscribe : subscribe
    },
    dataType : "json",
    success : [ajax_result_handler,
    function(data) {
      open_main_menu()
    }],
    error : [ajax_error_handler],
  })
}

function open_main_menu() {
  $("#main_menu li.subscription").remove()

  $.ajaxq("main_menu", {
    url : "select.php",
    type : "post",
    data : {
      main_menu : 1,
      customers_id : window.customers_id,
      user_id : window.user_id,
    },
    dataType : "json",
    success : [ajax_result_handler,
    function(data) {
      if ("subscribed" in data) {
        if (data.subscribed) {
          $("#main_menu").append($("<li class='subscription' title='Stop receiving copies of correspondence with this customer'>" + "<img src='/includes/graphics/subscribeg.png' /> Unsubscribe</li>").click(function() {
            subscribe(0)
          }))
        } else {
          $("#main_menu").append($("<li class='subscription' title='Start receiving copies of correspondence with this customer'>" + "<img src='/includes/graphics/subscribe.png' /> Subscribe</li>").click(function() {
            subscribe(1)
          }))
        }

      }
    }],
    error : [ajax_error_handler],
  })

  $("#main_menu").show({
    duration : 250
  })
}

function invoicing_load_ticket(button) {
  load_ticket($(button).data("ticket_id"), invoicing_ticket_onsave)
}

function invoicing_ticket_onsave(dialog) {
  //Gets called when ticket is saved
  $(dialog).dialog("destroy")
  refresh()
}

function invoicing_new_ticket_dialog(button) {
  new_ticket_dialog({
    title : $(button).data("title"),
    reference_type : $(button).data("reference_type"),
    reference : $(button).data("reference")
  }, function(dialog) {
    //gets called when ticket is saved
    $(dialog).dialog("destroy")
    refresh()
  })
}

function customer_ticket_onclick() {
  a = $("<a />").data({
    reference_type : "customers_id",
    reference : window.data.customer.customer.address.customers_id,
    title : "Ticket for " + window.data.customer.customer.address.name,
  }).get(0)

  window.invoicing_new_ticket_dialog(a)
}
