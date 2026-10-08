# commerce_product_outofstock_extra
A simplified `Out of Stock` experience is introduced by just a checkbox that marks that for a product variation. 

## The problem
When the user selects a variation that is marked as `out of stock` we need to take out (hide) the add to cart and replace it with a call for action (e.g. contact us)

## The fix
The modules is listening for a selection of a variant and injects classes that are used for controling the visibility of various html elements in the page. 
