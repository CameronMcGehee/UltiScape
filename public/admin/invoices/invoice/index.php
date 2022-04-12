<?php

	// Start Session
	require_once '../../../php/startSession.php';

	// This is the business select page so if we are not signed in, just redirect to the login page

	if (!isset($_SESSION['ultiscape_adminId'])) {
		header("location: ../../login");
	}

	require_once '../../../../lib/adminUIRender.php';
	$adminUIRender = new adminUIRender();

	// Other required libraries
	require_once '../../../../lib/table/admin.php';
	require_once '../../../../lib/table/invoice.php';
	require_once '../../../../lib/table/business.php';
	require_once '../../../../lib/table/docId.php';
	require_once '../../../../lib/render/etc/tagEditor.php';
	require_once '../../../../lib/table/invoiceItem.php';
	if (isset($_GET['id']) && !empty($_GET['id'])) {
		$currentInvoice = new invoice($_GET['id']);
		$currentDocId = new docId($currentInvoice->docIdId);
	} else {
		$currentInvoice = new invoice();
		$currentDocId = new docId($_SESSION['ultiscape_businessId']);
	}

	if ($currentInvoice->businessId != $_SESSION['ultiscape_businessId']) {
        header("location: ./");
		exit();
    }

	$currentBusiness = new business($_SESSION['ultiscape_businessId']);

	if ($currentInvoice->existed) {
		if ((string)$currentBusiness->docIdIsRandom == '1') {
			$titleName = 'Invoice '.$currentDocId->randomId;
		} else {
			$titleName = 'Invoice '.$currentDocId->incrementalId;
		}
	} else {
		$titleName = 'New Invoice';
	}

	echo $adminUIRender->renderAdminHtmlTop('../../../', htmlspecialchars($titleName), 'Edit '.htmlspecialchars($titleName).'.');
	echo $adminUIRender->renderAdminUIMenuToggleScripts('../../../');

	// Generate all the needed authTokens for the page
	require_once '../../../../lib/table/authToken.php';

	$mainAuthToken = new authToken();
	$mainAuthToken->authName = 'editInvoice';
	$mainAuthToken->set();

	$addItemAuthToken = new authToken();
	$addItemAuthToken->authName = 'addInvoiceItem';
	$addItemAuthToken->set();

	$updateItemsAuthToken = new authToken();
	$updateItemsAuthToken->authName = 'updateItems';
	$updateItemsAuthToken->set();

	$deleteItemAuthToken = new authToken();
	$deleteItemAuthToken->authName = 'deleteInvoiceItem';
	$deleteItemAuthToken->set();

?>

	<style>
		/* Hide scrollbar for Chrome, Safari and Opera */
		#twoColContentWrapper::-webkit-scrollbar {
			display: none;
		}

		/* Hide scrollbar for IE, Edge and Firefox */
		#twoColContentWrapper {
			-ms-overflow-style: none;  /* IE and Edge */
			scrollbar-width: none;  /* Firefox */
		}
	</style>

	<script src="../../../js/etc/animation/shake.js"></script>

	<script src="../../../js/etc/form/showFormError.js"></script>
	<script src="../../../js/etc/form/clearFormErrors.js"></script>

	<script>
		var formData;
		var scriptOutput;
		var invoiceId ='<?php echo $currentInvoice->invoiceId; ?>';
		var formState;
		var url = new URL(window.location.href);

		var isNewInvoice = <?php if ($currentInvoice->existed) {echo 'false';} else {echo 'true';} ?>;
		var lastChange = new Date();
		var changesSaved = true;
		var waitingForError = false;

		$(function() {

			if (isNewInvoice) {
				$("#firstLastName").focus();
			}

			$("#invoiceForm").submit(function(event) {
				event.preventDefault();
			});

			function setUnsaved() {
				$(".changesMessage").each(function () {
					$(this).html('<span style="color: gray; width: 10em;">⏳ Saving changes...</span>');
				});
				// $(".changesMessage").each(function () {
				// 	$(this).shake(50);
				// });
				changesSaved = false;
			}

			function inputChange (e) {
				setUnsaved();
				lastChange = new Date();
			}

			setInterval(() => {
				currentTime = new Date();
				if ((currentTime.getTime() - lastChange.getTime()) > 500 && !changesSaved) {
					checkChanges();
				}
			}, 1000);

			function setWaitingForError() {
				$(".changesMessage").each(function () {
					$(this).html('<span style="color: red;">Uh oh, fix the error!</span>');
				});
				$(".changesMessage").each(function () {
					$(this).shake(50);
				});
				waitingForError = true;
			}

			function setSaved() {
				$(".changesMessage").each(function () {
					$(this).html('<span style="color: green;">Up to date ✔</span>');
				});
				changesSaved = true;
				waitingForError = false;
			}

			if ($.isNumeric(url.searchParams.get('wsl'))) {
				$(".cmsMainContentWrapper").scrollTop(url.searchParams.get('wsl'));
			}

			function checkChanges() {
				$('.loadingGif').each(function() {
					$(this).fadeIn(100);
				});
				
				formData = $("#invoiceForm").serialize();
				
				$("#scriptLoader").load("./scripts/async/editinvoice.script.php", {
					invoiceId: invoiceId,
					formData: formData
				}, function () {
					scriptOutput = $("#scriptLoader").html().split(":::");
					invoiceId = scriptOutput[0];
					formState = scriptOutput[1];
					clearFormErrors();

					switch (formState) {
						case 'success':
							setSaved();
							if (isNewInvoice) {
								isNewInvoice = false;
								window.history.pushState("string", 'UltiScape (Admin) - New Invoice', "./?id="+invoiceId);
								window.location.reload();
							}
							break;
						default:
							setWaitingForError();
							showFormError("#"+formState+"Error", "#"+formState);
							$("#"+formState).shake(50);

							$('.loadingGif').each(function() {
								$(this).fadeOut(100);
							});
							break;
					}

					$('.loadingGif').each(function() {
						$(this).fadeOut(100);
					});
				});
				changesSaved = true;
			}

			window.onbeforeunload = function() {
				if (changesSaved == false || waitingForError == true) {
					return "Changes have not been saved yet. Are you sure you would like to leave?";
				} else {
					return;
				}
			};

			// Show and hide password button

			var pass_field = $("#password");

			$("#showPasswordButton").on("click", function () {
				if($("#showPasswordButton").html() == "Show"){
					$('#password').show(200);
					$("#showPasswordButton").html("Hide");
				} else {
					$('#password').hide(200);
					$("#showPasswordButton").html("Show");
				}
			})

			function updateTotals() {

				if (discountAmount == '' || discountAmount == ' ' || grandTotal < 0) {
					$("#discount").val(0);
					discountAmount = 0;
				}
				
				var subTotal = 0;
				var discountIsPercent = <?php if ((string)$currentInvoice->discountIsPercent == '1') {echo 'true';} else {echo 'false';} ?>;
				var totalTax = 0;
				var grandTotal = 0;
				var discountAmount = 0;

				$(".itemTotal").each(function () {
					var price = 0;
					var quantity = 0;
					var salesTax = 0;
					var itemTotalSalesTax = 0;
					var total = 0;

					price = $(this).closest('tr').find('input[name="itemPrice[]"').val();
					quantity = $(this).closest('tr').find('input[name="itemQuantity[]"').val();
					salesTaxInput = $(this).closest('tr').find('input[name="itemTax[]"').val();

					salesTax = salesTaxInput / 100;
					itemTotalSalesTax = (price * quantity) * (1 + (salesTax)) - (price * quantity);
					
					total = (price * quantity) * (1 + (salesTax));

					total = "$" + total.toFixed(2);
					$(this).html(total);

					totalTax = totalTax + itemTotalSalesTax;
					subTotal = subTotal + (price * quantity);
				});

				totalTaxOutput = totalTax.toFixed(2);
				subTotalOutput = subTotal.toFixed(2);
				grandTotal = subTotal + totalTax;

				if (discountIsPercent == false) {
					grandTotal = grandTotal - $("#discount").val();
					$("#discountOutput").html("-$" + $("#discount").val());
				} else {
					grandTotal = grandTotal * (1 - ($("#discount").val()/100));
					$("#discountOutput").html("-$" + (grandTotal * (($("#discount").val()/100))).toFixed(2));
				}
				
				grandTotalOutput = grandTotal.toFixed(2);

				$("#totalTax").html("$" + totalTaxOutput);
				$("#subTotal").html("$" + subTotalOutput);
				$("#grandTotal").html("$" + grandTotalOutput);

			}

			function registerItemDeleteButtonClicks() {
				$("span[id*='deleteItem:::']").each(function (i, el) {
					$(el).on('click', function(e) {
						currentId = this.id.split(":::")[1];
						$.post("./scripts/async/deleteItem.script.php", {
							itemId: currentId,
							deleteItemAuthToken: '<?php echo $deleteItemAuthToken->authTokenId; ?>'
						}, function () {
							// find the closest <tr> to the delete button and remove it.
							$(el).closest('tr').remove();
						});
					});
				});
			}

			registerItemDeleteButtonClicks();

			$("#add").click(function(event) {

				if (isNewInvoice) {
					checkChanges();
				} else {
					$('.addItemLoadingGif').fadeIn(100);

					// set a new item with script, and then add it to the list with it's Id

					setTimeout(() => {
						$("#scriptLoader").load("./scripts/async/addItem.script.php", {
							invoiceId: invoiceId,
							addItemAuthToken: '<?php echo $addItemAuthToken->authTokenId; ?>'
						}, function () {
							scriptOutput = $("#scriptLoader").html().split(":::");
							itemId = scriptOutput[0];
							formState = scriptOutput[1];

							switch (formState) {
								case 'success':

									// Append item to list
									$("#items").append('<tr><td><input type="hidden" name="itemId[]" value="' + itemId + '"><input class="invisibleInput" style="height: 1.3em; width: 16em; font-size: 1.3em;" type="text" name="itemName[]"> <span id="deleteItem:::' + itemId + '" class="smallButtonWrapper orangeButton xyCenteredFlex" style="width: 1em; display: inline;"><img style="height: 1em;" src="../../../images/ultiscape/icons/trash.svg"></span></td><td class="tg-0lax"><input class="invisibleInput" style="height: 1.3em; width: 5em; font-size: 1.3em;" type="number" step="0.01" name="itemPrice[]"  min="0" style="width: 5em;" value="25"></td><td class="tg-0lax"><input class="invisibleInput" style="height: 1.3em; width: 3em; font-size: 1.3em;" type="number" step="any" name="itemQuantity[]" min="1" style="width: 5em;" value="1"></td><td class="tg-0lax"><input class="invisibleInput" style="height: 1.3em; width: 3em; font-size: 1.3em;" type="number" step="any" name="itemTax[]" min="0" max="100"style="width: 5em;" value="0"><label for="itemTax">%</label></td><td class="tg-0lax"><span class="itemTotal"></span></td></tr>');
									updateTotals();

									// Make sure the new inputs update the changes tracker
									$("#invoiceForm :input").change(function () {
										inputChange();
									});
									registerItemDeleteButtonClicks()

									break;
								default:
									break;
							}

							$('.addItemLoadingGif').fadeOut(100);
						});
					}, 300);
				}
				
			});

			$("#itemsTable").on('click', '.remove', function(event) {
				event.preventDefault();
				$(this).closest('tr').remove();
				updateTotals();

				$("#invoiceForm :input").change(function () {
					inputChange();
				});
			});

			window.setInterval(updateTotals, 100);

			$("#invoiceForm :input").change(function () {
				inputChange();
			});
		});
	</script>
</head>

<body>
	<span style="display: none;" id="scriptLoader"></span>
	<div class="cmsBodyWrapper">

		<?php 
			echo $adminUIRender->renderAdminTopBar('../../../', true, true, true);
		?>

		<?php 
            echo $adminUIRender->renderAdminSideBar('../../../');
        ?>

		<div class="cmsMainContentWrapper textColorThemeGray styledText">
			<div class="mobileOnlyBlock xyCenteredFlex centered" style="position: sticky; top: 0px; width: 100%; padding-top: .3em; padding-bottom: .3em; border-bottom: .1em solid gray; background-color: white; z-index: 99;">
				<div class="changesMessage"><span style="color: green;">Up to date ✔</span></div>
				<img style="display: none; width: 2em;" src="../../../images/ultiscape/etc/loading.gif" class="loadingGif">
			</div>

				<div class="twoColPage-Content-InfoSmall maxHeight">
					<div id="twoColContentWrapper" class="paddingLeftRight90 maxHeight" style="overflow: auto;">

						<form class="defaultForm" id="invoiceForm">

							<input type="hidden" name="mainAuthToken" id="mainAuthToken" value="<?php echo htmlspecialchars($mainAuthToken->authTokenId); ?>">

							<br>

							<br>

							<h3>Invoice</h3>

							<div class="twoCol">
								<div>
									<label for="docId"><p>Doc ID</p></label>
									<input class="defaultInput" id="docId" type="number" step="1" name="docId" min="0" style="width: 5em;" value="<?php if ((string)$currentBusiness->docIdIsRandom == '1') {echo $currentDocId->randomId;} else {echo $currentDocId->incrementalId;}  ?>">
									<span id="docIdError" class="underInputError" style="display: none;"><br>Input a valid Id.</span>	
								</div>
								<div style="text-align: right;">
									<!-- <label for="customerSelector"><p>Customer</p></label> -->
									<!-- Select customer dialog -->
									<?php
									
										require_once '../../../../lib/render/input/customerSelector.php';
										$customerSelector = new customerSelector("customerSelector", ["name" => 'customer', "selectedId" => $currentInvoice->customerId]);
										$customerSelector->render();
										echo $customerSelector->output;

									?>
									<span id="customerError" class="underInputError" style="display: none;"><br>Select a customer.</span>
									<p>Customer Billing Address...</p>
								</div>
							</div>

							<br>

							<h3>Items</h3>

							<table class="defaultTable" style="width: 100%;" id="itemsTable">
								<tr id="tableHeader">
									<td style="text-decoration: underline;">Item</td>
									<td style="text-decoration: underline;">Price</td>
									<td style="text-decoration: underline;">Quantity</td>
									<td style="text-decoration: underline;">Sales Tax</td>
									<td style="text-decoration: underline;">Total</td>
								</tr>
								<tbody id="items">

								<?php

									$currentInvoice->pullItems("ORDER BY dateTimeAdded ASC");
									foreach ($currentInvoice->items as $itemId) {
										$currentItem = new invoiceItem($itemId);
										if ($currentItem->existed) {
											echo '<tr><td><input type="hidden" name="itemId[]" value="'.htmlspecialchars($itemId).'"><input class="invisibleInput" style="height: 1.3em; width: 16em; max-width: 30vw; font-size: 1.3em;" type="text" name="itemName[]" value="'.htmlspecialchars($currentItem->name).'"> 
											<span id="deleteItem:::'.htmlspecialchars($itemId).'" class="smallButtonWrapper orangeButton xyCenteredFlex" style="width: 1em; display: inline;"><img style="height: 1em;" src="../../../images/ultiscape/icons/trash.svg"></span>
											</td><td class="tg-0lax"><input class="invisibleInput" style="height: 1.3em; width: 5em; font-size: 1.3em;" type="number" step="0.01" name="itemPrice[]" value="'.htmlspecialchars($currentItem->price).'" min="0" style="width: 5em;" value="25"></td><td class="tg-0lax"><input class="invisibleInput" style="height: 1.3em; width: 3em; font-size: 1.3em;" type="number" step="any" name="itemQuantity[]" value="'.htmlspecialchars($currentItem->quantity).'" min="1" style="width: 5em;" value="1"></td><td class="tg-0lax"><input class="invisibleInput" style="height: 1.3em; width: 3em; font-size: 1.3em;" type="number" step="any" name="itemTax[]" value="'.htmlspecialchars($currentItem->tax).'" min="0" max="100"style="width: 5em;" value="0"><label for="itemTax">%</label></td><td class="tg-0lax"><span class="itemTotal"></span></td></tr>';
										}
									}

								?>

								</tbody>

								<tr id="subTotalRow">
									<td colspan="3"><a href="#" id="add">Add Item</a><img style="display: none; width: 2em;" src="../../../images/ultiscape/etc/loading.gif" class="addItemLoadingGif"></td>
									<td style="text-decoration: underline; border-top-width: 2px; border-left-width: 2px; border-left-color: green; border-top-color: green;">Subtotal:</td>
									<td id="subTotal" style="border-top-width: 2px; border-top-color: green;">$0</td>
								</tr>

								<tr id="totalTaxRow">
									<td colspan="3"></td>
									<td style="text-decoration: underline; border-left-width: 2px; border-left-color: green;">Total Tax:</td>
									<td id="totalTax">$0</td>
								</tr>

								<tr id="discountRow">
									<td colspan="3"></td>
									<td style="text-decoration: underline; border-left-width: 2px; border-left-color: green;">Discount:</td>
									<td id="discountOutput">-$0</td>
								</tr>

								<tr id="grandTotalRow">
									<td colspan="3"></td>
									<td style="text-decoration: underline; border-left-width: 2px; border-left-color: green;"><b>Grand Total:</b></td>
									<td><span style="font-size: 1.5em; color: green;" id="grandTotal">$0</span></td>
								</tr>
							</table>

							<br>
							<label for="discount"><p>Discount</p></label>
							<input class="defaultInput" id="discount" type="number" step="0.01" name="discount" min="0" style="width: 5em;" value="<?php echo htmlspecialchars($currentInvoice->discount); ?>">

							<br><br>

							<h3>Notes</h3>
							<div class="defaultInputGroup">
								<label for="notes"><p>Comments (included on invoice)</p></label>
								<textarea class="defaultInput" style="font-size: 1.2em; width: 95%;" name="comments" id="comments"><?php echo htmlspecialchars($currentInvoice->comments); ?></textarea>

								<br><br>
								
								<label for="notes"><p>Private (to Admins)</p></label>
								<textarea class="defaultInput" style="font-size: 1.2em; width: 95%;" name="privateNotes" id="privateNotes"><?php echo htmlspecialchars($currentInvoice->privateNotes); ?></textarea>
							</div>
							<br><br>

						</form>
						
					</div>

					<div id="twoColInfoWrapper" class="paddingLeftRight90 paddingTopBottom90">
						<br class="desktopOnlyBlock">
						<span class="desktopOnlyBlock">
							<div class="changesMessage"><span style="color: green;">Up to date ✔</span></div>
							<img style="display: none; width: 2em;" src="../../../images/ultiscape/etc/loading.gif" class="loadingGif">
						</span>

						<br><hr><br>

						<h3>Other Info</h3>

						<?php
							$addedDate = new DateTime($currentInvoice->dateTimeAdded);
						?>

						<p>Added on <?php echo $addedDate->format('D, d M y'); ?></p>
					</div>
				</div>
		</div>

		<?php
			echo $adminUIRender->renderAdminFooter('../../../');
		?>

		<?php 
			echo $adminUIRender->renderAdminMobileNavBar('../../../');
		?>

	</div>

	<?php
		echo $adminUIRender->renderAdminTopBarDropdowns('../../../');
	?>
</body>
<?php 
	echo $adminUIRender->renderAdminHtmlBottom('../../../');
?>