require 'test_helper'

class ProjectsControllerTest < ActionDispatch::IntegrationTest
  include Devise::Test::IntegrationHelpers

  setup do
    @admin_user   = users(:admin)
    @user_1001    = users(:user_1001)
    @user_1002    = users(:user_1002)
    @user_1003    = users(:user_1003)
    @project_1001 = projects(:project_1001)
    @project_1002 = projects(:project_1002)
    @project_1003 = projects(:project_1003)

    @project_default = projects(:project_default)
  end


  #----------
  # SHOW 
  #----------
  test "1 NonAdmin users can view only projects thst belong to them" do 
    sign_in @user_1001
    get project_url(@project_1001)
    assert_response :success
    find_text_in_form('Title for Project 2001')

    get project_url(@project_1002)
    assert_response :redirect
    get response.headers['Location']
    find_text_in_form('Access denied')
  end
  test "2 Admin user able to view anyone's projects" do
    sign_in @admin_user 
    get project_url(@project_1001)
    assert_response :success
    find_text_in_form('Title for Project 2001')

    get project_url(@project_1002)
    assert_response :success
    find_text_in_form('Title for Project 2002')

    get project_url(@project_1003)
    assert_response :success
    find_text_in_form('Title for Project 2003') 
  end

  #----------
  # UPDATE 
  #----------
  test "3 NonAdmin users able to UPDATE only their own project" do 
    sign_in @user_1001
    patch project_url(@project_1001), params: { project: { id: @project_1001.id, title: 'New Project name', statement: 'New statement', owner: @project_1001.owner_id }}
    assert_redirected_to projects_url
    get response.headers['Location']
    find_text_in_form('successfully updated')

    patch project_url(@project_1002), params: { project: { id: @project_1002.id, title: 'New Project name', statement: 'New statement', owner: @project_1002.owner_id } }
    assert_response :redirect
    get response.headers['Location']
    find_text_in_form('Access denied')
  end
  test "4 Admin users able to UPDATE any project" do 
    sign_in @admin_user
    patch project_url(@project_1001), params: { project: { id: @project_1001.id, title: 'New Project name 1', statement: 'New statement 1', owner: @project_1001.owner_id }}
    assert_redirected_to projects_url
    get response.headers['Location']
    find_text_in_form('successfully updated')

    patch project_url(@project_1002), params: { project: { id: @project_1002.id, title: 'New Project name 2', statement: 'New statement 2', owner: @project_1002.owner_id } }
    assert_redirected_to projects_url
    get response.headers['Location']
    find_text_in_form('successfully updated')

    patch project_url(@project_1003), params: { project: { id: @project_1003.id, title: 'New Project name 3', statement: 'New statement 3', owner: @project_1003.owner_id } }
    assert_redirected_to projects_url
    get response.headers['Location']
    find_text_in_form('successfully updated')
  end


  #----------
  # INDEX
  #----------
  test "5 Admin users able to get an index of anyone's projects" do 
    sign_in @admin_user
    get projects_url   
    find_text_in_form('All Projects')
  end
  test "6 NonAdmin users able to get an index of their projects" do 
    sign_in @user_1001
    get projects_url   
    find_text_in_form('All Projects')
  end

  #----------
  # NEW
  #----------
  test "7 Admin users able to view new project form" do 
    sign_in @admin_user
    get new_project_url
    find_text_in_form('Create a New Project')
  end
  test "8 NonAdmin users able to view new project form" do 
    sign_in @user_1001
    get new_project_url
    find_text_in_form('Create a New Project')
  end

  #----------
  # CREATE
  #----------
  test "9 Admin users able to create a new project" do
    sign_in @admin_user
    assert_difference('Project.count') do
      post projects_url, params: { project: { id: 99, title: 'New Project 99', statement: 'New statement 99', owner: @admin_user.id }} 
    end
    assert_response :redirect
    get response.headers['Location']
    find_text_in_form('successfully created')
  end
  test "10 NonAdmin users able to create a new project" do
    sign_in @user_1001
    assert_difference('Project.count') do
      post projects_url, params: { project: { id: 99, title: 'New Project 99', statement: 'New statement 99', owner: @user_1001.id }} 
    end
    assert_response :redirect
    get response.headers['Location']
    find_text_in_form('successfully created')
  end

  #----------
  # EDIT
  #----------
  test "11 Admin users able to edit anyone's project" do
    sign_in @admin_user
    get edit_project_url(@project_1001) 
    assert_response :success
    find_text_in_form('Edit Project Settings')

    get edit_project_url(@project_1002) 
    assert_response :success
    find_text_in_form('Edit Project Settings')
  end
  test "12 NonAdmin users able to edit only their projects" do
    sign_in @user_1001
    get edit_project_url(@project_1001) 
    assert_response :success
    find_text_in_form('Edit Project Settings')

    get edit_project_url(@project_1002) 
    assert_response :redirect
    get response.headers['Location']
    find_text_in_form('Access denied')
  end

  #----------
  # DESTROY
  #----------
  test "13 Admin users able to delete anyone's project except for default" do
    sign_in @admin_user

    assert_difference('Project.count', 0) do
      delete project_url(@project_default)
    end

    assert_difference('Project.count', -1) do
      delete project_url(@project_1001)
    end
    assert_redirected_to projects_url
    get response.headers['Location']
    find_text_in_form('successfully deleted.')
  end
  test "14 NonAdmin users able to delete only their projects except for default" do
    sign_in @user_1003

    assert_difference('Project.count', 0) do
      delete project_url(@project_default)
    end

    assert_difference('Project.count', -1) do
      delete project_url(@project_1003)
    end
    assert_redirected_to projects_url
    get response.headers['Location']
    find_text_in_form('successfully deleted.')

    delete project_url(@project_1002)
    assert_response :redirect
    get response.headers['Location']
    find_text_in_form('Access denied')
  end


end
