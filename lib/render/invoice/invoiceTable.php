<?php

    require_once dirname(__FILE__)."/../render.php";

    class invoiceTable extends render {

        private business $currentBusiness; // For storing the object of the business

        public string $renderId = '';
        public array $options = [];
        private $currentDate;

        function __construct(string $renderId, array $options = []) {

            parent::__construct();

            if (!isset($options['rootPathPrefix'])) {
				$options['rootPathPrefix'] = './';
			}

            if (!isset($options['queryParams'])) {
				$options['queryParams'] = '';
			}

            if (!isset($options['businessId'])) {
				if (isset($_SESSION['ultiscape_businessId'])) {
                    $options['businessId'] = $_SESSION['ultiscape_businessId'];
                } else {
                    throw new Exception("No businessId set to pull invoices from (in invoiceTable)");
                }
			}

            if (!isset($options['maxRows']) || !is_numeric($options['maxRows'])) {
				$options['maxRows'] = 10;
			}

            if (!isset($options['pageGetVarName'])) {
				$options['pageGetVarName'] = '-p';
			}

            if (!isset($options['sortGetVarName'])) {
				$options['sortGetVarName'] = '-s';
			}

            if (!isset($options['usePage']) || !is_numeric($options['usePage'])) {
				$options['usePage'] = 1;
			}

            if (!isset($options['showAdd'])) {
				$options['showAdd'] = false;
			}
            
            if (!isset($options['showSort'])) {
				$options['showSort'] = false;
			}

            if (!isset($options['useSort']) || !in_array($options['useSort'], ['az', 'za', 'newest', 'oldest'])) {
				$options['useSort'] = 'az';
			}

            if (!isset($options['showPageNav'])) {
				$options['showPageNav'] = true;
			}

            if (!isset($options['showCustomer'])) {
				$options['showCustomer'] = true;
			}


            if (!isset($options['showTotal'])) {
				$options['showTotal'] = true;
			}

            if (!isset($options['showItems'])) {
				$options['showItems'] = false;
			}

            if (!isset($options['showAge'])) {
				$options['showAge'] = false;
			}

            if (!isset($options['showPaymentStatus'])) {
				$options['showPaymentStatus'] = true;
			}

            if (!isset($options['showDateAdded'])) {
				$options['showDateAdded'] = false;
			}

            if (!isset($options['showFunctions'])) {
				$options['showFunctions'] = true;
			}

            if (!isset($options['showBatch'])) {
				$options['showBatch'] = false;
			}

            $this->currentBusiness = new business($options['businessId']);

            $this->renderId = $renderId;

            require_once dirname(__FILE__)."/../../table/authToken.php";
            require_once dirname(__FILE__)."/../../table/invoice.php";
            require_once dirname(__FILE__)."/../../table/docId.php";
            require_once dirname(__FILE__)."/../../table/business.php";
            // require_once dirname(__FILE__)."/../etc/tagEditor.php";
            require_once dirname(__FILE__)."/../etc/pageNavigator.php";
            require_once dirname(__FILE__)."/../etc/sortBySelector.php";
            require_once dirname(__FILE__)."/../../etc/time/diffCalc.php";

            // Page
            if (isset($_GET[$renderId.$options['pageGetVarName']])) {
                $options['usePage'] = $_GET[$renderId.$options['pageGetVarName']];
            }
            // Sort
            if (isset($_GET[$renderId.$options['sortGetVarName']])) {
                $options['useSort'] = $_GET[$renderId.$options['sortGetVarName']];
            }

            $this->options = $options;

            $this->currentDate = new DateTime;
            $this->currentDate = $this->currentDate->format('Y-m-d H:i:s');
        }

        function render() {
            $this->output = '';

            $firstLimit = ($this->options['usePage'] - 1) * $this->options['maxRows'];

            // Get count for page count
            $pageCountQuery = "WHERE businessId = '".$_SESSION['ultiscape_businessId']."'";
            if ($this->options['queryParams'] != '') {
                $pageCountQuery .= ' '.$this->options['queryParams'];
            }
            $selectAll = $this->db->select('invoice', "COUNT(invoiceId) AS num", $pageCountQuery);

            // Start div for table header (create invoice button and nav)
            if ($this->options['showAdd'] || $this->options['showSort'] || $this->options['showPageNav']) {
                $this->output .= '<div style="display: grid; grid-template-columns: 20% 80%; grid-template-rows: 1.5em; grid-template-areas: "1 2";">';

                // Render the add invoice button
                $this->output .= '<div class="yCenteredFlex" style="width: 6em;">';
                if ($this->options['showAdd']) {
                    $this->output .= '<a class="smallButtonWrapper greenButton noUnderline yCenteredFlex" href="'.$this->options['rootPathPrefix'].'admin/invoices/invoice">➕ New</a>';
                }
                $this->output .= '</div>';
                
                // Render the page navigator, sort-by selector, and search bar
                if ($selectAll) {

                    if (is_array($selectAll) && (int)$selectAll[0]['num'] > 0) {
                        $pageNav = new pageNavigator(ceil(($selectAll[0]['num'] / $this->options['maxRows'])), $this->options['usePage'], './', $this->renderId.'-p', 'float: right; padding: .2em;');
                        if ($this->options['showPageNav']) {
                            $pageNav->render();
                        }

                        $sortBySelector = new sortBySelector($this->renderId."sortSelector", './', $this->renderId.$this->options['sortGetVarName'], $this->options['useSort']);
                        $sortBySelector->style = 'width: 5em;';
                        
                        if ($this->options['showSort']) {
                            $sortBySelector->render();
                        }
                    }

                    if (isset($sortBySelector)) {
                        $sortBySelectorOutput = $sortBySelector->output;
                    } else {
                        $sortBySelectorOutput = '';
                    }

                    if (isset($pageNav)) {
                        $pageNavOutput = $pageNav->output;
                    } else {
                        $pageNavOutput = '';
                    }

                    $this->output .= '<div><span style="height: 100%; float:right; margin-right: .3em;" class="yCenteredFlex">'.$pageNavOutput.'</span> <span style="width: min-content; height: 100%; float:right; margin-right: .3em;" class="yCenteredFlex">'.$sortBySelectorOutput.'</span></div>';
                }
                
                // End div for table header
                $this->output .= '</div>';
            }

            // Get actual results
            $params = '';

            switch ($this->options['useSort']) {
                case 'newest':
                    $params .= 'ORDER BY dateTimeAdded DESC ';
                    break;
                case 'oldest':
                    $params .= 'ORDER BY dateTimeAdded ASC ';
                    break;
                default:
                    break;
            }

            if (empty($this->options['queryParams'])) {
                $params .= "LIMIT ".$firstLimit.", ".$this->options['maxRows'];
            } else {
                $params .= $this->options['queryParams']." LIMIT ".$firstLimit.", ".$this->options['maxRows'];
            }

            $this->currentBusiness->pullInvoices($params);
            
			if (count($this->currentBusiness->invoices) < 1) {
				$this->output .= '<table class="defaultTable" style="margin-top: .5em;"><tr><td class="la">No invoices...</td></tr></table>
                ';
                return;
			}

			$this->output .= '<table class="defaultTable highlightOdd hoverHighlight" style="margin-top: .5em;">
            ';
            
            $this->output .= '<tr>
            ';
            
            
            if ($this->options['showBatch']) {
                $this->output .= '<th class="ca nrb">✔</th>
                ';
            }

            $this->output .= '<th class="la nrb">ID</th>
            ';

            if ($this->options['showCustomer']) {
                $this->output .= '<th class="ca nrb nlb">Customer</th>
                ';
            }
            if ($this->options['showTotal']) {
                $this->output .= '<th class="ca desktopOnlyTable-cell nrb nlb">Total</th>
                ';
            }
            if ($this->options['showItems']) {
                $this->output .= '<th class="la nlb">Items</th>
                ';
            }
            if ($this->options['showAge']) {
                $this->output .= '<th class="ca desktopOnlyTable-cell nlb">Age</th>
                ';
            }
            if ($this->options['showPaymentStatus']) {
                $this->output .= '<th class="ca nlb">Payment Status</th>
                ';
            }
            if ($this->options['showFunctions']) {
                $this->output .= '<th class="ca desktopOnlyTable-cell nlb">Functions</th>
                ';
            }

            $this->output .= '</tr>
            ';
			
			foreach ($this->currentBusiness->invoices as $invoiceId) {

                $invoice = new invoice($invoiceId);
                $docId = new docId($invoice->docIdId);

                if ((string)$this->currentBusiness->docIdIsRandom == '1') {
                    $docIdOutput = $docId->randomId;
                } else {
                    $docIdOutput = $docId->incrementalId;
                }
                $customerNameOutput = 'NF';
                $totalOutput = 'NF';
                $paymentStatusOutput = 'NF';

                // Render the row
				$this->output .= '<tr>';
                if ($this->options['showBatch']) {
                    $this->output .= '<td class="ca nrb" style="width: 2em;"><input class="defaultInput" type="checkbox" name="'.$this->renderId.'-checkbox" value="'.htmlspecialchars($invoice->invoiceId).'"></td>';
                }
                $this->output .= '<td class="la nrb vam" style="max-width: 10em;"><a href="'.$this->options['rootPathPrefix'].'admin/invoices/invoice?id='.htmlspecialchars(htmlspecialchars($invoice->invoiceId)).'" style="font-size: 1.1em; margin-right: .5em;"><b>'.$docIdOutput.'</b></a></td>
                ';
                                    
                if ($this->options['showCustomer']) {
                    $this->output .= '<td class="la nlb nrb">'.$customerNameOutput.'</td>
                    ';
                }
                if ($this->options['showTotal']) {
                    $this->output .= '<td class="la desktopOnlyTable-cell nrb nlb">'.$totalOutput.'</td>
                    ';
                }
                if ($this->options['showAge']) {
                    $diffOutput = getDateTimeDiffString($invoice->dateTimeAdded, $this->currentDate);
                    $this->output .= '<td class="ca nlb">('.$diffOutput.')</td>
                    ';
                }
                if ($this->options['showItems']) {
                    $this->output .= '<td class="la desktopOnlyTable-cell nrb nlb">'.$itemsOutput.'</td>
                    ';
                }
                if ($this->options['showPaymentStatus']) {
                    $this->output .= '<td class="la desktopOnlyTable-cell nrb nlb">'.$paymentStatusOutput.'</td>
                    ';
                }
                if ($this->options['showDateAdded']) {
                    $diffOutput = getDateTimeDiffString($invoice->dateTimeAdded, $this->currentDate);
                    $dateAddedOutput = new DateTime($invoice->dateTimeAdded);
                    $dateAddedOutput = $dateAddedOutput->format('m/d/Y');
                    $this->output .= '<td class="ca desktopOnlyTable-cell nlb">'.htmlspecialchars($dateAddedOutput).' ('.$diffOutput.' ago)</td>
                    ';
                }

                if ($this->options['showFunctions']) {
                    $this->output .= '<td class="ca desktopOnlyTable-cell nlb">Print | Email | Delete</td>
                    ';
                }

                $this->output .='</tr>
                ';
			
            }

			$this->output .= '</table>';

            // Batch Operations

            if ($this->options['showBatch']) {
                $deleteInvoicesAuthToken = new authToken;
                $deleteInvoicesAuthToken->authName = 'deleteInvoices';
                $deleteInvoicesAuthToken->set();
                $this->output .= '<div style="margin-top: .5em;font-size: .9em;"><p>With selected: 
                <select class="defaultInput" id="batchSelect">
                    <option value="none">Nothing</option>
                    <option value="delete">❌ Delete</option>
                </select> <button class="defaultInput" onclick="'.$this->renderId.'batchOperation()">Go</button></p>
                
                <script>
                    var deleteInvoicesAuthToken = "'.$deleteInvoicesAuthToken->authTokenId.'";
                    function '.$this->renderId.'batchOperation() {
    
                        var allChecked = document.querySelectorAll("input[name='.$this->renderId.'-checkbox]:checked");
    
                        var checkedArray = Array.from(allChecked).map(checkbox => checkbox.value);
    
                        if ($("#batchSelect option:selected").val() == "delete" && checkedArray.length > 0) {
                                $("#scriptLoader").load("'.$this->options['rootPathPrefix'].'admin/scripts/async/invoice/deleteInvoices.php", {"invoices[]": checkedArray, "authToken": deleteInvoicesAuthToken}, function() {
                                    if ($("#scriptLoader").html() == "success") {
                                        document.location.reload(true);
                                    }
                                });
                        }
                    }
                
                </script>
                
                </div>';
            }
            
        }

    }

?>