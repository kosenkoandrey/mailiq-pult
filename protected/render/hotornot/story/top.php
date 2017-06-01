<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="http://www.glamurnenko.ru/favicon.ico">

    <title>Glamurnenko.ru - Hot or not</title>

    <link href="<?= APP::Module('Routing')->root ?>public/modules/hotornot/poll/bootstrap.min.css" rel="stylesheet">
    <link href="<?= APP::Module('Routing')->root ?>public/modules/hotornot/poll/ie10-viewport-bug-workaround.css" rel="stylesheet">
    <!--[if lt IE 9]><script src="<?= APP::Module('Routing')->root ?>public/modules/hotornot/poll/ie8-responsive-file-warning.js"></script><![endif]-->
    <script src="<?= APP::Module('Routing')->root ?>public/modules/hotornot/poll/ie-emulation-modes-warning.js"></script>
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <link href="<?= APP::Module('Routing')->root ?>public/modules/hotornot/poll/style.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP::Module('Routing')->root ?>public/modules/hotornot/poll/jquery-ui.css">
    <link rel="stylesheet" href="<?= APP::Module('Routing')->root ?>public/modules/hotornot/poll/font-awesome.min.css">
    <link rel="stylesheet" href="<?= APP::Module('Routing')->root ?>public/modules/hotornot/poll/magnific-popup.css">
    <link href="https://fonts.googleapis.com/css?family=Roboto" rel="stylesheet">
    
    <style>
        body {
            background-image: url(https://pult.glamurnenko.ru/public/WebApp/resources/views/full/langs/ru_RU/types/extensions/EBilling/products/pages/garderob100/sale/images/bg4.jpg);
            background-repeat:repeat;
            padding: 0;
            margin: 0;
            font-family: 'Roboto', sans-serif;
            line-height: 25px;
        }
        #logo {
            position: absolute;
            right: 0;
            left: 0;
            z-index: 1030;
            text-align: center;
            padding: 50px;
        }
        .top {
            margin-top: 0;
        }
        .top table {
            
        }
        .photo {
            width: 120px;
        }
        .num {
            text-align: center;
        }
        .head {
            text-align: center;
            font-weight: bold;
            width: 17%
        }
        .tooltip, .tooltip-inner {
            width: 600px !important;
            max-width: 600px !important;
        }
        </style>
    </head>
    <body>
        <div class="container">
            <div id="logo">
                <img src="<?= APP::Module('Routing')->root ?>public/modules/hotornot/images/logo.png"><img src="<?= APP::Module('Routing')->root ?>public/modules/hotornot/images/hotornot.png">
            </div>
            <div class="row">
                <div class="col-sm-12" style="margin-top: 160px; text-align: center">
                    <b style="font-size: 30px;">Результаты конкурса</b>
                    <br><br>
                    Спасибо всем, кто прислал истории. Спасибо всем, кто проголосовал. Запали в душу много рассказов. Запомнилась история Марины - шерифа из США (!); тронула сердце девочка, которая хочет осуществить мамину мечту; поразила фантазией Светлана, когда сравнила стилиста со сталкером.
                    <br><br>
                    Конечно, хотелось подарить подарки всем. Но по договоренности выбирала одного из top-5 по голосованию.
                    <br><br>
                    Победила Елена. Вот кусочек из её истории:
                    <br><br>
                    — Позвольте мне предложить вам померить вот это платье. Женщина, была удивлена, но померила предложенное мной платье.
                    <br><br>
                    Но все равно хочу отметить еще одну историю. Это будет бонусное награждение. Мне понравилась история Олены. Ей бонус за историю — обучение в Школе, но без проверки домашних заданий. Вот кусочек из её истории:
                    <br><br>
                    Люблю его давно. Его можно описать одним словом: «дорого». Ну, или двумя: «очень дорого». Иногда кажется, что он создан, чтобы лелеять во мне комплекс неполноценности. Но сегодня решила — пора предстать пред ним, заявить о себе.
                    <br><br>
                    Прочитать истории победителей и все остальные вы можете ниже
                </div>
            </div>
            <div class="row top">
                <div class="col-sm-12">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 10%"></th>
                                <th style="width: 45%"></th>
                                <th style="width: 15%" class="head"></th>
                                <th style="width: 10%" class="head">За</th>
                                <th style="width: 10%" class="head">Против</th>
                                <th style="width: 10%" class="head">Итого</th>
                                
                            </tr>
                        </thead>
                        <tbody>
                            <?
                            foreach ($data as $key => $value) {
                                ?>
                                <tr>
                                    <td><br><h1 style="font-size: 76px"><?= $key + 1 ?></h1></td>
                                    <td class="photo"><div style="padding: 15px 0"><?= $value['story']['caption'] ?></div></td>
                                    <td class="num"><br><h1 class="show-story" data-original-title="<?= htmlspecialchars($value['story']['text']) ?>" class="matrisHeader" style="font-size: 36px; margin: 25px 0 0 0; padding: 0; display: inline-block; cursor: pointer; border-bottom: 1px dashed #000000">История</h1></td>
                                    <td class="num"><br><h1 style="font-size: 76px"><?= $value['hot'] ?></h1></td>
                                    <td class="num"><br><h1 style="font-size: 76px"><?= $value['not'] ?></h1></td>
                                    <td class="num"><br><h1 style="font-size: 76px"><?= $value['total'] ?></h1></td>
                                </tr>
                                <?
                            }
                            ?>
                            
                        </tbody>
                    </table>
                    <?
                    if (!isset(APP::Module('Routing')->get['all'])) {
                        ?>
                        <br>
                        <a target="_top" href="http://pult2.glamurnenko.ru/hotornot/story/top?all" class="btn btn-danger btn-lg" style="display: block; margin-bottom: 50px">Показать всех</a>
                        <?
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
            <div class="modal-dialog" role="document">
              <div class="modal-content">
                  <div class="modal-body" style="white-space: pre-wrap;">
                    xxx
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-default" data-dismiss="modal">Закрыть</button>
                </div>
              </div>
            </div>
        </div>

        <script src="<?= APP::Module('Routing')->root ?>public/modules/hotornot/poll/ie10-viewport-bug-workaround.js"></script>
        <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
        <script src="http://getbootstrap.com/dist/js/bootstrap.min.js"></script>
        <script src="<?= APP::Module('Routing')->root ?>public/modules/hotornot/poll/jquery.magnific-popup.js"></script>
        
        <script>
        $('.photo-popup-link').magnificPopup({type:'image'});
        $("[data-toggle='tooltip']").tooltip();
        
        $('.show-story').on('click', function() {
            $('#myModal .modal-body').html($(this).data('original-title'));
            $('#myModal').modal('show');
        });
        </script>
    </body>
</html>