class Users::RegistrationsController < Devise::RegistrationsController
  before_action :configure_permitted_parameters

  def index
    @users = User.all
  end

  def show
    @user = current_user
  end

  def edit
  end

  def new
    super
  end


  def generate_new_password_email
    user = User.find(params[:user_id])
    user.send_reset_password_instructions
    flash[:notice] = "Reset password instructions have been sent to #{user.email}."
    redirect_to admin_user_path(user)
  end


  def edituser
    if user_is_admin?(current_user) 
      flash[:info] =  "Registration: Admin editing different user - #{params.inspect}"
      @user = User.find(params[:id])
      render 'edit'
    else
      flash[:danger] = "Registration: can't edit user; not an Admin."
      redirect_to root_url
    end
  end


  def destroy     
    @user = current_user
    @user.email = "#{@user.email}_DISABLED"
    @user.confirmed_at = nil
    @user.save
  end


  def create
    @user = User.new(user_params)

    if @user.first_name.empty? || @user.last_name.empty? | @user.email.empty?
      flash[:danger] = "Missing required field(s)"
      render 'new'
    else
      @user.id = SecureRandom.uuid
      if @user.save
        
        User.add_default_project(@user)
        User.add_role( @user.id, Role.find_by( :name => 'User' ).id )

        flash[:info] =  "Registration: Please check your email to activate your account."
        redirect_to root_url
      else
        render 'new'
      end
    end
  end


  def update
    @user = current_user

    if params[:user][:first_name].empty? || params[:user][:last_name].empty? || params[:user][:email].empty?
      flash[:danger] = "Missing required field(s)"
      render 'edit'
    else
      if @user.update_attributes(user_params)
        bypass_sign_in(@user)

        flash[:success] = "Profile updated"
        redirect_to root_url
      else
        flash[:danger] = "Couldn't update profile: #{@user.errors.messages}"
        redirect_to edit_user_registration_url
      end
    end
  end




  private

    def user_params
        params.require(:user).permit(:first_name, :middle_name, :last_name, :email,  :password, :password_confirmation, :admin)
    end


  protected

    def update_resource(resource, params)
      resource.update_without_password(params)
    end

    def configure_permitted_parameters
      devise_parameter_sanitizer.permit(:sign_up,        keys: [:first_name, :middle_name, :last_name, :admin, :email] )
      devise_parameter_sanitizer.permit(:account_update, keys: [:first_name, :middle_name, :last_name, :admin, :email] )
    end



end
