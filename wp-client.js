/* global wp_ajax_url */// Added in the plugin via wp_localize_script

class PrintAppWoo extends PrintAppClient {
    constructor(params) {
        super(params);
        this.params = params;
        this.selectors = {};
        this.selectors.qryCartBtn = '.single_add_to_cart_button,.kad_add_to_cart,.addtocart,#add-to-cart,.add_to_cart,#add,#AddToCart,#product-add-to-cart,#add_to_cart,#button-cart,#AddToCart-product-template,.product-details-wrapper .add-to-cart,.btn-addtocart,.ProductForm__AddToCart,.add_to_cart_product_page,#addToCart,[name="add"],[data-button-action="add-to-cart"],#Add,#form-action-addToCart';
        this.on('app:ready', this.createBtns);
        this.on('app:saved', this.saveProject);
        this.readyComm();
        
    }
    
    readyComm() {
        const req   = new XMLHttpRequest();
        this.comm   = {
            post: (url, input) => new Promise( (res,rej) => {
                const data = new FormData();
                Object.keys(input).forEach(key=>{
                    data.append(key, input[key]);
                });
                req.onreadystatechange = function() {
                    if (req.readyState == 4) {
                        if (req.status == 200) 
                            res(req.responseText);
                        else
                            rej(req.responseText);
                    }
                };
                req.open('post', url);
                req.send(data);
            })
        }
    }
    
    createBtns() {
        const cartBtn = document.querySelector(this.selectors.qryCartBtn);
        if (cartBtn) {
            this.launch_btn = document.createElement('button');
            this.launch_btn.innerText = 'Launch Designer';
            this.launch_btn.onclick = e => this.paw_startEditor(e);
            this.launch_btn.classList.add('px-6');
            this.launch_btn.style['margin-right'] = '10px';
            if (this.params.mode != 'new-project')
                this.launch_btn.style.display = 'none';
            cartBtn.parentElement.prepend(this.launch_btn);
            
            this.reset_btn = document.createElement('button');
            this.reset_btn.innerText = 'Reset Project';
            this.reset_btn.onclick = e => this.paw_resetProject(e);
            this.reset_btn.classList.add('px-6');
            this.reset_btn.classList.add('paw_edit');
            this.reset_btn.style['margin-right'] = '10px';
            if (this.params.mode == 'new-project')
                this.reset_btn.style.display = 'none';
            cartBtn.parentElement.prepend(this.reset_btn);
            
            this.edit_btn = document.createElement('button');
            this.edit_btn.innerText = 'Edit Project';
            this.edit_btn.onclick = e => this.paw_startEditor(e);
            this.edit_btn.classList.add('px-6');
            this.edit_btn.classList.add('paw_edit');
            this.edit_btn.style['margin-right'] = '10px';
            if (this.params.mode == 'new-project')
                this.edit_btn.style.display = 'none';
            cartBtn.parentElement.prepend(this.edit_btn);
        }
    }
    
    async paw_resetProject(e) {
        e.preventDefault();
        const data = { 'product_id': this.params.product.id, action: 'print_dot_app_reset_project' };
        await this.comm.post(wp_ajax_url, data);
        window.location.reload()
        
    }
    
    paw_startEditor(e) {
        e.preventDefault();
        this.showApp();
    }
    
    showEditBtns() {
        this.launch_btn.style.display = 'none';
        this.edit_btn.style.display = 'block';
        this.reset_btn.style.display = 'block';
    }
    
    async saveProject(e) {
        const projectId = e.data.projectId, 
            data = {
                'action': 'print_dot_app_save_project',
                'value': JSON.stringify(e.data),
                'product_id': this.params.product.id
            };
        await this.comm.post(wp_ajax_url,data);
        this.showEditBtns();
    }
}