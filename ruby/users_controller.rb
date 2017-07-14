class UsersController < ApplicationController
  include Pundit
  require 'json'

  before_action :user_is_signed_in 
  after_action :verify_authorized


  def index 
    @users = User.order(:first_name) 
    authorize @users
  end

  def show
    @user = User.find( params[:id] )
    authorize @user
  end


  def new 
    @title = "[Admin] Create a New User Profile"
    @user = User.new
    authorize @user
    prefill_user_form

    @current_role_id = Role.find_by(name: 'User').id 
  end


  def create 
    attr = user_params_pwd
    @user = User.new(attr) 
    authorize @user

    @user.id = @user.id.present? ? @user.id : SecureRandom.uuid

    if @user.save 
      User.add_default_project(@user)

      log_activity(  @user, "New user [#{@user.email}] created" )      
      if params['authorized_ids'].present?
        ids = JSON.parse(params['authorized_ids'])
        ids.each do |s|                             
          us           = UserSource.new                
          us.source_id = s
          us.user_id   = @user['id']
          us.id        = SecureRandom.uuid

          if !us.save
            logger.warn "Couldn't save source id [#{s}] for this user"
          end
        end
      end

      User.add_role(@user.id, params['user_role']['role_id'])

      flash[:info] = "Profile created; have user check their email to activate their account."
      redirect_to user_url(@user)
    else
      flash[:danger] = "Couldn't save user profile; check required fields"
      redirect_to new_user_path
    end
  end


  

  def edit 
    @user = User.find(params[:id])
    authorize @user

    whos = current_user == @user ? 'My' : 'User'
    @title = "Editing #{whos} Profile"
    prefill_user_form

    if @current_role_id.blank? 
      @current_role_id = Role.find_by(name: 'User').id 
    end
  end



  def update 
    me = current_user
    @user = User.find(params[:id])
    authorize @user

    attr = params[:user][:password].blank? ? user_params : user_params_pwd

    if @user.update_attributes(attr)
      log_activity(  @user, "User [#{@user.email}] updated" )      

      if UserSource.find_by(user_id: @user.id)
        UserSource.where(user_id: @user.id).all.destroy_all 
      end

      if !params['authorized_ids'].blank?
        ids = JSON.parse(params['authorized_ids'])
        ids.each do |s|                             
          us           = UserSource.new  
          us.source_id = s
          us.user_id   = @user.id
          us.id        = SecureRandom.uuid

          if !us.save
            logger.warn "Couldn't save source id [#{s}] for this user"
          end
        end
      end

      ur = UserRole.find_by(user_id: @user.id)
      if !params['user_role'].nil?
        ur.role_id = params['user_role']['role_id']
        ur.id      = SecureRandom.uuid

        if !ur.save                                     
          logger.warn "Couldn't save role id [#{params['user_role']['role_id']}] for this user" 
        end
      end


      flash[:success] = "Profile updated."
      sign_in me
      redirect_to user_url(@user)
    else
      flash[:danger] = "Couldn't update user profile; check required fields"  
      redirect_to  edit_user_path   
    end
  end


  def destroy 
    @user = User.find(params['id'])
    authorize @user

    @user.destroy
    log_activity( @user, "User [#{@user.email}] deleted" )   

    flash[:success] = "User was successfully deleted"
    redirect_to users_url
  end





  private 

    def user_params
        params.require(:user).permit( :id, :first_name, :middle_name, :last_name, :email, :bio, :admin ) 
    end
    def user_params_pwd
        params.require(:user).permit( :id, :first_name, :middle_name, :last_name, :email, :bio, :admin, :password, :password_confirmation )
    end


    def prefill_user_form
      @roles = Role.all.collect {|p| [ p.name, p.id ] }
      @source_groups = SourceGroup.order('name').collect {|p| [ p.name, p.id ] }
      @sources = Hash.new
      groups = SourceGroup.order('name').collect {|p| [ p.name, p.id ] }
      groups.each do |g|
        @sources[g[0]] = Source.where(source_group_id: g[1]).all 
      end

      if current_user
        @current_role_id          = []
        @current_authorized_names = []
        @current_authorized_ids   = []

        @user.user_roles.each   do |r| @current_role_id.push(r.role.id) end
        @user.user_sources.each do |s| @current_authorized_names.push(s.source.name) end
        @user.user_sources.each do |s| @current_authorized_ids.push(s.source.id) end
        
        @current_role_id          = @current_role_id.join(", ")  
        @current_authorized_names = @current_authorized_names.join(", ")
        @current_authorized_ids   = @current_authorized_ids.join(", ")
      end
    end

end
