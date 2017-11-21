<?php $payment_systems = $this->getPaymentSystems();

if(is_array($payment_systems) && !empty($payment_systems)){ ?>
    <button type="button" class="sel-ps-ik btn btn-info btn-lg" data-toggle="modal" data-target="#InterkassaModal"
            style="display: none;">
        Select Payment Method
    </button>

    <div id="InterkassaModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" id="plans">
                <div class="container">
                    <h1>
                        1.Выбрать платежный инструмент<br>
                        2.Выбрать валюту<br>
                        3.Нажать "Оплатить"
                    </h1>

                    <div class="row">
                        <?php foreach($payment_systems as $ps => $info): ?>
                        <div class="col-sm-3 text-center payment_system">
                            <div class="panel panel-warning panel-pricing">
                                <div class="panel-heading">
                                    <div class="panel-image">
                                        <img src="/netcat_template/class/interkassa/images/<?=$ps?>.png"
                                             alt="<?=$info['title']?>">
                                    </div>
                                    <!--<h3><?=$info['title']?></h3>-->
                                </div>
                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="radioBtn btn-group">
                                        <?php foreach($info['currency'] as $currency => $currencyAlias): ?>
                                            <a class="btn btn-primary btn-sm notActive"
                                               data-toggle="fun" data-title="<?=$currencyAlias?>"><?=$currency?></a>
                                        <?php endforeach;?>
                                        </div>
                                        <input type="hidden" name="fun">
                                    </div>
                                </div>
                                <div class="panel-footer">
                                    <a class="btn btn-lg btn-block btn-success ik-payment-confirmation"
                                       data-title="<?=$ps?>"
                                       href="#">Оплатить через<br>
                                        <strong><?=$info['title']?></strong>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach;?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
else
    echo $payment_systems;
