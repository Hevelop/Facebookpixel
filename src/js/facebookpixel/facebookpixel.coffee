cookieAddToCart = 'facebookpixel_add'
cookieRemoveFromCart = 'facebookpixel_remove'
googleAnalyticsUniversalData = googleAnalyticsUniversalData or 'shoppingCartContent': []

getCookie = (name) ->
  cookie = ' ' + document.cookie
  search = ' ' + name + '='
  setStr = null
  offset = 0
  end = 0
  if cookie.length > 0
    offset = cookie.indexOf(search)
    if offset != -1
      offset += search.length
      end = cookie.indexOf(';', offset)
      if end == -1
        end = cookie.length
      setStr = unescape(cookie.substring(offset, end))
  setStr

delCookie = (name) ->
  cookie = name + '=' + '; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/; domain=.' + window.location.host
  document.cookie = cookie
  return

FacebookPixelCart = ->
  @productQtys = []
  @origProducts = {}
  @productWithChanges = []
  @addedProducts = []
  @removedProducts = []
  return

FacebookPixelCart.prototype =
  subscribeProductsUpdateInCart: ->
    context = this
    $$('[data-cart-item-update]').each (element) ->
      $(element).stopObserving('click').observe 'click', ->
        context.updateCartObserver()
        return
      return
    $$('[data-multiship-item-update]').each (element) ->
      $(element).stopObserving('click').observe 'click', ->
        context.updateMulticartCartObserver()
        return
      return
    $$('[data-cart-empty]').each (element) ->
      $(element).stopObserving('click').observe 'click', ->
        context.emptyCartObserver()
        return
      return
    return
  emptyCartObserver: ->
    @collectOriginalProducts()
    for i of @origProducts
      if i != 'length' and @origProducts.hasOwnProperty(i)
        product = Object.extend({}, @origProducts[i])
        @removedProducts.push product
    @cartItemRemoved()
    return
  updateMulticartCartObserver: ->
    @collectMultiProductsWithChanges()
    @collectProductsForMessages()
    @cartItemAdded()
    @cartItemRemoved()
    return
  updateCartObserver: ->
    @collectProductsWithChanges()
    @collectProductsForMessages()
    @cartItemAdded()
    @cartItemRemoved()
    return
  collectMultiProductsWithChanges: ->
    @collectOriginalProducts()
    @collectMultiCartQtys()
    @productWithChanges = []
    groupedProducts = {}
    i = 0
    while i < @productQtys.length
      cartProduct = @productQtys[i]
      if typeof groupedProducts[cartProduct.id] == 'undefined'
        groupedProducts[cartProduct.id] = parseInt(cartProduct.qty, 10)
      else
        groupedProducts[cartProduct.id] += parseInt(cartProduct.qty, 10)
      i++
    for j of groupedProducts
      if groupedProducts.hasOwnProperty(j)
        if typeof @origProducts[j] != 'undefined' and groupedProducts[j] != @origProducts[j].qty
          product = Object.extend({}, @origProducts[j])
          product['qty'] = groupedProducts[j]
          @productWithChanges.push product
    return
  collectProductsWithChanges: ->
    @collectOriginalProducts()
    @collectCartQtys()
    @productWithChanges = []
    i = 0
    while i < @productQtys.length
      cartProduct = @productQtys[i]
      if typeof @origProducts[cartProduct.id] != 'undefined' and cartProduct.qty != @origProducts[cartProduct.id].qty
        product = Object.extend({}, @origProducts[cartProduct.id])
        if parseInt(cartProduct.qty, 10) > 0
          product['qty'] = cartProduct.qty
          @productWithChanges.push product
      i++
    return
  collectOriginalProducts: ->
    if googleAnalyticsUniversalData and googleAnalyticsUniversalData['shoppingCartContent']
      @origProducts = googleAnalyticsUniversalData['shoppingCartContent']
    return
  collectMultiCartQtys: ->
    productQtys = []
    $$('[data-multiship-item-id]').each (element) ->
      productQtys.push
        'id': $(element).readAttribute('data-multiship-item-id')
        'qty': $(element).getValue()
      return
    @productQtys = productQtys
    return
  collectCartQtys: ->
    productQtys = []
    $$('[data-cart-item-id]').each (element) ->
      productQtys.push
        'id': $(element).readAttribute('data-cart-item-id')
        'qty': $(element).getValue()
      return
    @productQtys = productQtys
    return
  collectProductsForMessages: ->
    @addedProducts = []
    @removedProducts = []
    i = 0
    while i < @productWithChanges.length
      product = @productWithChanges[i]
      if typeof @origProducts[product.id] != 'undefined'
        if product.qty > @origProducts[product.id].qty
          product.qty = Math.abs(@origProducts[product.id].qty - (product.qty))
          @addedProducts.push product
        else if product.qty < @origProducts[product.id].qty and product.qty != 0
          product.qty = product.qty - (@origProducts[product.id].qty)
          @addedProducts.push product
        else
          product.qty = Math.abs(product.qty - (@origProducts[product.id].qty))
          @removedProducts.push product
      i++
    return
  formatProductsArray: (productsIn) ->
    productsOut = []
    itemId = undefined
    for i of productsIn
      if i != 'length' and productsIn.hasOwnProperty(i)
        if typeof productsIn[i]['sku'] != 'undefined'
          itemId = productsIn[i].sku
        else
          itemId = productsIn[i].id
        productsOut =
          content_name: productsIn[i].name
          content_ids: productsIn[i].id
          content_type: 'product'
          value: productsIn[i].price,
          currency: productsIn[i].currency
          product_catalog_id: productsIn[i].product_catalog_id
    productsOut
  cartItemAdded: ->
    if @addedProducts.length == 0
      return
    fbq 'track', 'AddToCart', @formatProductsArray(@addedProducts) if fbq?
    @addedProducts = []
    return
  cartItemRemoved: ->
    if @removedProducts.length == 0
      return
    #remove cart item
    @removedProducts = []
    return
  parseAddToCartCookies: ->
    if getCookie(cookieAddToCart)
      @addedProducts = []
      addProductsList = decodeURIComponent(getCookie(cookieAddToCart))
      @addedProducts = JSON.parse(addProductsList)
      delCookie cookieAddToCart
      @cartItemAdded()
    return
  parseRemoveFromCartCookies: ->
    if getCookie(cookieRemoveFromCart)
      @removedProducts = []
      removeProductsList = decodeURIComponent(getCookie(cookieRemoveFromCart))
      @removedProducts = JSON.parse(removeProductsList)
      delCookie cookieRemoveFromCart
      @cartItemRemoved()
    return
facebookpixelcart = new FacebookPixelCart
document.observe 'dom:loaded', ->
  facebookpixelcart.parseAddToCartCookies()
  facebookpixelcart.parseRemoveFromCartCookies()
  #FacebookPixelCart.subscribeProductsUpdateInCart()
  return
