<?php
	/**
	 *		author:exp;
	 *		email:root@evilexp.me;
	 */
namespace Home\Controller;
use Think\Controller;

class PostController extends Controller {

	/**
	 *		发布闲置
	 */
	public function post() {

		if($_SESSION['user']['flag']) {
			if($_POST['sub']) {

				$type = M("type");
				$map['id'] = $_POST['type3'];

				// 得到父类型
				$type3 = $type -> where($map) -> select();
				// 商品父类id
				$typeId = $_POST['type3'];
				// 商品父类path
				$typePath = $type3[0]['path'];

				// 组合类型路径
				$typePath = $typePath."-".$typeId;

				$_POST['typeId'] = $typeId;
				$_POST['typePath'] = $typePath;

				$date = date("Y-m-d H:i:s",time());
				$_POST['postTime'] = $date;

				// 发布人
				$_POST['sellerId'] = $_SESSION['user']['id']; 

				$goods = D("goods");
				if($goods -> create()) {

					// 如果post中goodsId有值说明是修改信息
					if($_POST['goodsId']) {
						if($_FILES['photos']['name'][0]) {
							$gpic = M("goodspic");
							$id = $_POST['goodsId'];
							$gid['goodsId'] = $id;
							if($imgs = $gpic -> where($gid) -> select()) {

								// 得到该商品的所有的图片并删除
								foreach($imgs as $value) {
									$url = "./Public/Home/".$value['goodsPic'];
									unlink($url);
								}

								$gpic -> where($gid) -> delete();
							}
							$this -> upload($id);
						}

						if($goods -> save()) {
							$this -> redirect("Post/sell");
							exit;
						} else {
							$this -> error("修改商品信息失败");
							exit;
						}

						// 否则就是添加
						// goodsId得到此商品的id
					} else if($goodsId = $goods -> add()) {

						// 上传
						$this -> upload($goodsId);
						$this -> redirect("Post/sell");
						exit;
					} else {
						$this -> error("发布失败");
						exit;
					}
				} else {
					$this -> error($goods -> getError());
					exit;
				}
			}

			// 修改商品信息时先查出以前的数据
			if($_GET) {
				$goods = M("goods");
				$goodsMsg['goodsId'] = $_GET["id"];
				$data = $goods -> where($goodsMsg) -> select();

				// 把商品以前的路径分隔出来作为三级联动的默认值
				$typePath = $data[0]["typePath"];
				$typePath = explode("-",$typePath);
				$data[0]['typePath1'] = $typePath[1];
				$data[0]['typePath2'] = $typePath[2];
				$data[0]['typePath3'] = $typePath[3];

				$this -> assign("data",$data[0]);
			}

			$this -> type();
			$this -> display();
		} else {
			$this -> redirect("Index/index");
		}
	}

	/**
	 *		查询数据库中的商品类别
	 */
	public function type() {
		if($_GET['id']) {
			$goods = M("goods");
			$goodsMsg['goodsId'] = $_GET["id"];
			$data = $goods -> where($goodsMsg) -> select();

			// 把商品以前的路径分隔出来作为三级联动的默认值
			$typePath = $data[0]["typePath"];
			$typePath = explode("-",$typePath);
		}

		$type = M("type");

		// path长度是1位是第一类
		$type1 = $type -> where("length(path) = 1") -> select();
		//$typeId1['typePath1'] = $typePath[1];

		// 把这个typeId分配到模板上用户判断默认选中
		//$this -> assign("typeId",$typeId1);
		$this -> assign("ty1",$type1);

		// 如果请求了第二类,就通过get传递的t2作为parentId去查询第二类的内容
		if($_GET['t2']) {
			$map['parentId'] = $_GET['t2'];
			$type2 = $type -> where($map) -> select();
			echo json_encode($type2);
		}
		$this -> assign("ty2",$type2);

		// 如果请求了第三类,就通过get传递的t3作为parentId去查询第三类的内容
		if($_GET['t3']) {
			$map['parentId'] = $_GET['t3'];
			$type3 = $type -> where($map) -> select();
			echo json_encode($type3);
		}
		$this -> assign("ty3",$type3);
	}

	/**
	 *		上传图片
	 */
	private function upload($goodsId) {
		$upload = new \Think\Upload();
		$upload -> maxSize = 10000000;
		$upload -> exts = array("jpeg","jpg","png","gif");
		$upload -> rootPath = "./Public/Home/";
		$upload -> savePath = "Upload/";
		$info = $upload -> upload();

		$goodsPic = M("goodspic");
		$map['goodsId'] = $goodsId;

		foreach($info as $value) {
			$map['goodsPic'] = $value['savepath'].$value['savename'];
			if($goodsPic -> create($map)) {
				$goodsPic -> add();
			} else {
				$this -> error("上传文件出错");
				exit;
			}
		}
	}

	/**
	 *		我的闲置
	 */
	public function sell() {
		if($_SESSION['user']['flag']) {
			$goods = M("goods");
			$goodsPic = M("goodspic");

			// 获取用户id
			$map['sellerId'] = $_SESSION['user']['id'];

			// 分页
			$count = $goods ->where($map) -> count();
			$page = new \Think\Page($count,5);
			$show = $page -> show();

			// 得到所有该用户发布的所有商品
			$data = $goods -> where($map) -> order("status,goodsId desc") -> limit($page -> firstRow.",".$page -> listRows) -> select();

			if(!$data) {
				$this -> display("nullSell");
				exit;
			}

			$com = M("comment");

			// 通过商品id得到所有该用户发布的商品的图片
			foreach($data as &$value) {
				$id = $value['goodsId'];
				$goodsId['goodsId'] = $id;
				$map['goodsId'] = $id;

				// 查找是否有评价
				$comData = $com -> where($map) -> select();
				if($comData) {
					$value['comment'] = $comData[0]['content'];
				}

				// 此商品的所有图片
				$picData[$id] = $goodsPic -> where($goodsId) -> select();

				// 每个商品显示一张图片
				$value['goodsPic'] = $picData[$id][0]['goodsPic'];
			}


			$this -> assign("datas",$data);
			$this -> assign("page",$show);
			//$this -> assign("picDatas",$picData);
			$this -> display();
		} else {
			$this -> redirect("Index/index");
		}
	}

	/**
	 *		购物车
	 */
	public function cart() {
		
		// 得到当前用户的id
		if($_SESSION['user']['flag']) {
			$userId = $_SESSION['user']['id'];

			// 根据cart中的goodsId去goods中查找
			$Model = M();
			$datas = $Model -> where("cart.goodsId = goods.goodsId and cart.buyerId = $userId and goods.sellerId = user.id") -> table("__CART__ cart, __USER__ user, __GOODS__ goods") -> order("cart.id desc") -> select();

			if(!$datas) {
				$this -> display("null");
				exit;
			}

			// 通过商品id得到所有该用户发布的商品的图片
			$goodsPic = M("goodspic");
			foreach($datas as $key => &$value) {
				$goodsId['goodsId'] = $value['goodsId'];

				// 此商品的所有图片
				$picData = $goodsPic -> where($goodsId) -> select();

				// 每个商品显示一张图片
				$value['goodsPic'] = $picData[0]['goodsPic'];

				//	算出某类商品的总价 
				$datas[$key]['amounts'] = number_format($datas[$key]['amount'] * $datas[$key]['price'], 2,'.','');
			}

			$this -> assign("datas",$datas);
			$this -> display();
		} else {
			$this -> redirect("Index/index");
		}
	}


	/**
	 *		删除发布的商品
	 */
	public function del() {
		$id = $_GET['id'];
		$imgId['goodsId'] = $_GET['id'];

		// 删除商品
		$goods = M("goods");
		if($goods -> delete($id)) {
			//$this -> redirect("Post/sell");
		} else {
			$this -> error("删除失败111");
		}

		// 删除商品图片
		$goodsPic = M("goodspic");
		
		// 得到该商品的所有的图片并删除
		$pics = $goodsPic -> where($imgId) -> select();
		foreach($pics as $value) {
			$url = "./Public/Home/".$value['goodsPic'];
			unlink($url);
		}

		// 删除购物车中的商品
		$cart = M("cart");
		$datas = $cart -> where($imgId) -> select();
		if($datas) {
			if($cart -> where($imgId) -> delete()) {
				$this -> redirect("Post/sell");
			} else {
				$this -> error("删除失败222");
			}
		}

		// 从数据库中删除该商品所有的图片;
		if($goodsPic -> where($imgId) -> delete()) {
			$this -> redirect("Post/sell");
		} else {
			$this -> error("删除失败333");
		}
	}
	
	/**
	 *	删除购物车中的内容 
	 */
	public function delCart() {
		//$map = explode("-",$_GET['gid']);
		$map['goodsId'] = $_GET['gid'];
		$map['buyerId'] = $_GET['buyerId'];
		$cart = M("cart");

		if(!$cart -> where($map) -> delete()) {
			$this -> error("删除失败.");
		}
	}

	/**
	 *		验证码
	 */
	public function code() {
		$code = new \Think\Verify();
		$code -> entry();
	}

	/**
	 *		检测验证码
	 */
	private function check($c) {
		$code = new \Think\Verify();
		return $code -> check($c);
	}

	/**
	 *		后台验证
	 */
	public function tpcheck() {
		if($_POST['sub']) {
			$user = D("user");

			if($user -> create()) {
				if($user -> add()) {
					var_dump($_POST);
					exit;
				} else {
					$this -> error($user -> getError());
				}
			} else {
				$this -> error($user -> getError());
			}
		}
		$this -> display();
	}
}
