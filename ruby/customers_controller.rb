class CustomersController < ApplicationController
  before_filter :login_required
  before_filter :find_customer, :only => [:show, :edit, :update, :add_cust_admin, :delete_cust_admin]
  
  access_control do
    allow :admin
    allow :project_manager, :of => :customer, :to => [:show, :edit, :add_cust_admin, :delete_cust_admin]
    allow :task_creator, :of => :customer, :to => [:show]
    allow :sr_editor, :of => :customer, :to => [:show]
  end
  
  def find_customer
    @customer = Customer.find(params[:id])
  end

  def index
    @customers = Customer.all
  end
  
  def show
    @hk_user = HK::User.find(@customer.userid)
    @task_creators = @customer.customer_admins('task_creator')
    @sr_editors = @customer.customer_admins('sr_editor')
    @address = HK::Address.active_address(@customer.userid)
    @phone_number = HK::PhoneNumber.home_phone(@address)
  end
  
  def new
    @customer = Customer.new
  end
  
  def create    
    @customer = Customer.new
    @customer.init_with_defaults
    @customer.userid = params[:customer][:userid]
    if @customer.save
      flash[:notice] = "Successfully created customer with id #{@customer.userid.to_s}"
      redirect_to edit_customer_path(@customer)
    else
      render :action => 'new'
    end
  end
  
  def edit
    @hk_user = HK::User.find(@customer.userid)
    @address = HK::Address.active_address(@customer.userid)
    @phone_number = HK::PhoneNumber.home_phone(@address)
  end
  
  def update
    if @customer.update_attributes(params[:customer])
      flash[:notice] = "Successfully updated customer."
      redirect_to @customer
    else
      render :action => 'edit'
    end
  end
  
  def destroy
    @customer = Customer.find(params[:id])
    @customer.destroy
    flash[:notice] = "Successfully destroyed customer."
    redirect_to customers_url
  end
  
  def add_cust_admin
    case params[:commit]
    when 'Add new Task Creator'
      @customer.add_cust_admin('task_creator', params[:new_admin])
      flash[:notice] = "Successfully added new Task Creator"
    when 'Add new Senior Editor'
      @customer.add_cust_admin('sr_editor', params[:new_admin])
      flash[:notice] = "Successfully added new Senior Editor"
    else
      flash[:error] = "Invalid request to add a customer admin"
    end
    redirect_to @customer
  end
  
  def delete_cust_admin
    type = case params[:admin_type]
    when 'Task Creators'
      'task_creator'
    when 'Senior Editors'
      'sr_editor'
    else
      flash[:error] = "Invalid request to delete customer admin!"
      redirect_to @customer and return
    end
    @customer.delete_cust_admin(type, params[:user_id])
    flash[:notice] = "Successfully deleted customer administrator"
    redirect_to @customer
  end
end
