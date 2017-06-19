<?php
    $accessToken =getenv('CHANNEL_ACCESS_TOKEN');

    //ユーザーからのメッセージ取得
    $json_string = file_get_contents('php://input');
    $json_object = json_decode($json_string);

    //取得データ
    $replyToken = $json_object->{"events"}[0]->{"replyToken"};        //返信用トークン
    $userID = $json_object->{"events"}[0]->{"source"}->{"userId"};
    $message_type = $json_object->{"events"}[0]->{"message"}->{"type"};    //メッセージタイプ
    $message_text = $json_object->{"events"}[0]->{"message"}->{"text"};    //メッセージ内容


    //メッセージタイプが「text」以外のときは何も返さず終了
    if($message_type != "text") exit;




    //リプライメッセージ
    function sending_messages($accessToken, $replyToken, $message_type,$count){
      //返信メッセージ
      $rand = rand(0,8);
      if ($rand == 0) {
        $return_message_text = "ふーん";
      } elseif ($rand ==1) {
        $return_message_text = "それで？";
      } elseif ($rand == 2) {
        $return_message_text = "ほうほう";
      } elseif ($rand == 3 ) {
        $return_message_text = "wwwwwwww";
      } elseif ($rand == 4) {
        $return_message_text = "......";
      } elseif ($rand == 5) {
        $return_message_text = "いいね！！";
      } elseif ($rand == 6) {
        $return_message_text = "こいつ..さては...";
      } elseif ($rand == 7) {
        $return_message_text = "なんて優秀な学生なんだ！！";
      } else {
        $return_message_text = "採用！！！！！！！！！";
      }

      if ($count == 5) {
        $return_message_text = "面接は終わっているんだよ！！";
      }
        //レスポンスフォーマット
        $response_format_text = [
            "type" => $message_type,
            "text" => $return_message_text
        ];

        //ポストデータ
        $post_data = [
            "replyToken" => $replyToken,
            "messages" => [$response_format_text]
        ];

        //curl実行
        $ch = curl_init("https://api.line.me/v2/bot/message/reply");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charser=UTF-8',
            'Authorization: Bearer ' . $accessToken
        ));
        $result = curl_exec($ch);
        curl_close($ch);
    }

    //データベース部分
    function database_save($message_text,$userID,$count){

      $db = parse_url($_SERVER['CLEARDB_DATABASE_URL']);
      $db['dbname'] = ltrim($db['path'], '/');
      $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8";
      //データベース接続
      try {
        $db = new PDO($dsn, $db['user'], $db['pass']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      } catch (PDOException $e) {
        error_log($e);
        exit('データベースに接続できませんでした。'.$e->getMessage());
      }

      //データの取り出し→新規ユーザーなのか判断する
        $stmt = $db->prepare("SELECT mobile_id FROM line_user_info");//mobile_idを取り出して、新規ユーザーなのか検索
        $status = $stmt->execute();
        $result = $stmt->fetchall(PDO::FETCH_COLUMN);//全件取得して、連想配列で取得
        $result = array_values($result);//連想配列を通常の配列に変換
        $judge = in_array($userID,$result);

      if ($judge == false && $count == 0) {//新規ユーザーであった時
        //データベースに値を入力
        $stmt = $db->prepare('INSERT INTO line_user_info(id,name,count,mobile_id)VALUES(NULL, :name, :count,:mobile_id)');
        $stmt->bindValue(':name', $message_text, PDO::PARAM_STR);  //Integer（数値の場合 PDO::PARAM_INT)
        $stmt->bindValue(':count', 1, PDO::PARAM_INT);
        $stmt->bindValue(':mobile_id', $userID, PDO::PARAM_STR);
        $status = $stmt->execute();
     }else {//既存のユーザーであった時。発言回数によって更新する箇所を変更
       if ($count == 1) {
         $stmt = $db->prepare("UPDATE line_user_info SET age=$message_text,count=2 WHERE mobile_id LIKE '%$userID%'");
         $stmt->bindValue(':age', $message_text, PDO::PARAM_INT);
         $status = $stmt->execute();
       } elseif ($count == 2) {
         $stmt = $db->prepare("UPDATE line_user_info SET question1='$message_text',count=3 WHERE mobile_id LIKE '%$userID%'");
         $stmt->bindValue(':question1', $message_text, PDO::PARAM_STR);
         $status = $stmt->execute();
       } elseif ($count == 3) {
         $stmt = $db->prepare("UPDATE line_user_info SET question2='$message_text',count=4 WHERE mobile_id LIKE '%$userID%'");
         $stmt->bindValue(':question2', $message_text, PDO::PARAM_STR);
         $status = $stmt->execute();
       } elseif ($count == 4) {
         $stmt = $db->prepare("UPDATE line_user_info SET question3='$message_text',count=5 WHERE mobile_id LIKE '%$userID%'");
         $stmt->bindValue(':question3', $message_text, PDO::PARAM_STR);
         $status = $stmt->execute();
       }

       }
    }

    //プッシュメッセージ
    function push_messages($accessToken, $message_type,$userID,$count){
      // error_log($count);
      if($count == 1){
        $pushMessage ="年齢はいくつですか？";
      } elseif ($count == 2) {
        $pushMessage = "今まで苦労したことは？";
      } elseif ($count == 3) {
        $pushMessage ="今まで楽しかったことは？";
      } elseif ($count == 4) {
        $pushMessage ="最後に自己PRをお願いいたします";
      } elseif ($count == 5){
        $pushMessage ="これで面接は以上になります。ありがとうございました。";
      }
        //レスポンスフォーマット
      $response_format_text = [
          "type" => $message_type,
          "text" => $pushMessage
      ];

      //ポストデータ
      $post_data = [
          "to" => $userID,
          "messages" => [$response_format_text]
      ];

      //curl実行
      $ch = curl_init("https://api.line.me/v2/bot/message/push");
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json; charser=UTF-8',
          'Authorization: Bearer ' . $accessToken
      ));
      $result = curl_exec($ch);
      curl_close($ch);
    }

    function countcheck($userID){
      $db = parse_url($_SERVER['CLEARDB_DATABASE_URL']);
      $db['dbname'] = ltrim($db['path'], '/');
      $dsn = "mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8";

      try {
        $db = new PDO($dsn, $db['user'], $db['pass']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // var_dump($pdo->getAttribute(PDO::ATTR_SERVER_VERSION));
      } catch (PDOException $e) {
        error_log($e);
        exit('データベースに接続できませんでした。'.$e->getMessage());
      }

      $stmt = $db->prepare("SELECT count FROM line_user_info WHERE mobile_id LIKE '%$userID%'");
      $status = $stmt->execute();
      $count = $stmt->fetch(PDO::FETCH_ASSOC);
      $count = $count[count];
      return $count;
    }

//関数実行
$count =countcheck($userID);
sending_messages($accessToken, $replyToken, $message_type,$count);
$count =countcheck($userID);
if($count >= 5){
  exit;
} elseif ($count < 5) {
  database_save($message_text,$userID,$count,$count);
  $count =countcheck($userID);
  push_messages($accessToken, $message_type,$userID,$count);
}

?>
