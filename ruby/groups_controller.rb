class GroupsController < ApplicationController
  helper :workers
  
  before_filter :login_required

  # Need an @customer before calling the action
  before_filter :get_customer
  # Need an @project to for access control
  before_filter :get_project
  
  access_control do
    allow :admin
    allow :project_manager, :of => :customer, :only => [:index, :create, :destroy]
    allow :sr_editor, :of => :customer, :only => [:show]
  end
  
  def index
    @groups = Group.all
    @allGroups = HK::SimpleGroup.find(:all, :order => 'title')
    @role = params[:role]
    @from = params[:from]
    if @from == 'task'
      @task = @project.tasks.find(params[:task_id])
    end
  end
  
  def show
    @role = params[:role]
    @from = params[:from]
    if @from == 'customer'; @group = @customer.groups.find(params[:id])
    elsif @from == 'project'; @group = @project.groups.find(params[:id])
    else
      @task = @project.tasks.find(params[:task_id])
      @group = @task.groups.find(params[:id]) 
    end
    @users = @group.users
  end
    
  # OPTMIZE: Move all group management down into the group model, which will have to know if it is managing a project group or a task group
  def create
    if params[:from] == 'project'
      create_group_for_project(params)
    elsif params[:from] == 'task' 
      create_group_for_task(params.merge({:notify_users => true}))
    elsif params[:from] == 'customer'
      create_group_for_customer(params)
    end
  end
  
  def destroy
    if params[:from] == 'project'
      remove_group_from_project(params)
    elsif params[:from] == 'task' 
      remove_group_from_task(params)
    elsif params[:from] == 'customer'
      remove_group_from_customer(params)
    end
  end
  
  private
  def get_customer
    @customer = Customer.find(params[:customer_id])    
  end
  
  def get_project
    @project = @customer.projects.find(params[:project_id]) if params[:from] != 'customer'
  end
  
  def create_group_for_project(params)
    @project.unassign_worker(params[:role])
    @project.delete_group(params[:role])
    @group = @project.groups.build(:simple_group_id => params['HK::SimpleGroup'][:id], 
                                   :worker_role => params[:role])
    if @project.save
      flash[:notice] = "Successfully assigned group to project."
      redirect_to edit_customer_project_path(@customer, @project)
    else
      flash[:error] = "Unable to assign group to project"
      @groups = Group.all
      @allGroups = HK::SimpleGroup.find(:all, :order => 'title')
      @role = params[:role]
      render :action => 'index'
    end
  end
  
  def remove_group_from_project(params)
    @project.delete_group(params[:role])
    if @project.save
      flash[:notice] = "Successfully cleared group from project."
      redirect_to edit_customer_project_path(@customer, @project)
    else
      flash[:error] = "Unable to clear group from project"
      @groups = Group.all
      @allGroups = HK::SimpleGroup.find(:all, :order => 'title')
      @role = params[:role]
      render :action => 'index'
    end
  end

  def create_group_for_task(params)
    @task = @project.tasks.find(params[:task_id])
    @task.delete_group(params[:role])
    @task.unassign_worker(params[:role])
    @group = @task.groups.build(:simple_group_id => params['HK::SimpleGroup'][:id], 
                                :worker_role => params[:role])
    @group.add_workers(@task)
    if @task.save
      @task.reload
      flash[:notice] = "Successfully assigned group to task."
      if params[:notify_users]
        notification_type_id =  NotificationType.notification_id('assignment_to_claim')
        @group.user_ids.each do |user_id|
          Notification.add({:user_id => user_id, :task_id => @task.id, :role => params['role'], :notification_type_id => notification_type_id})
        end
      end
      redirect_to edit_customer_project_task_path(@customer, @project, @task)
    else
      flash[:error] = "Unable to assign group to task"
      @groups = Group.all
      @allGroups = HK::SimpleGroup.find(:all, :order => 'title')
      @role = params[:role]
      render :action => 'index'
    end
  end
  
  def remove_group_from_task(params)
    @task = @project.tasks.find(params[:task_id])
    @task.delete_group(params[:role])
    if @task.save
      flash[:notice] = "Successfully unassigned group from task."
      redirect_to edit_customer_project_task_path(@customer, @project, @task)
    else
      flash[:error] = "Unable to unassign group from task"
      @groups = Group.all
      @allGroups = HK::SimpleGroup.find(:all, :order => 'title')
      @role = params[:role]
      render :action => 'index'
    end
  end
  
  def create_group_for_customer(params)
    @customer.unassign_worker(params[:role])
    @customer.delete_group(params[:role])
    @group = @customer.groups.build(:simple_group_id => params['HK::SimpleGroup'][:id], 
                                   :worker_role => params[:role])
    if @customer.save
      flash[:notice] = "Successfully assigned group to customer."
      redirect_to edit_customer_path(@customer)
    else
      flash[:error] = "Unable to assign group to project"
      @groups = Group.all
      @allGroups = HK::SimpleGroup.find(:all, :order => 'title')
      @role = params[:role]
      render :action => 'index'
    end  
  end
  
end
