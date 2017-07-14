require 'hk/pen_name'
require 'hk/status'
require 'hk/user'

class Customer < ActiveRecord::Base
  attr_accessible :userid, :pmid, :delivery_email, :writer_terms, 
                  :deliver_with_formatting, :task_creator_payment, :sr_editor_payment, :task_reviewer_payment,
                  :writer_payment, :editor_payment, :fact_checker_payment, :final_reviewer_payment, 
                  :approver_payment, :description, :is_active, :total_revenue
  validates_presence_of :userid
  validates_uniqueness_of :userid
  has_many :projects, :dependent => :destroy # Needs to be destroy so that objects owned by a project are destroyed
  has_many :groups, :as => :groupable, :dependent => :destroy
  #has_one :penname, :class_name => 'HK::PenName', :readonly => true, :primary_key => :userid, :foreign_key => :user_id
  
  acts_as_authorization_object
  
  before_update :update_pm
  
                  
  def validate
    errors.add(:userid, "must be an active Helium User") unless HK::User.is_user_valid?(userid) == true
  end
  
  # Called after _Base::save_ regardless of a +create+ or +update+ action
  # Implements the feature that if the Customer is made inactive, 
  # all of its projects are made inactive.
  def after_save
    unless is_active
      projects.each do |project|
        project.is_active = false
        project.save!
      end
    end
  end
  
  def customer_name
    res = HK::User.find(userid, :select => 'first_name, last_name')
    res.first_name + " " + res.last_name
  end
  
  def default_pen_name
    default_status_id = HK::Status.status_id('default')
    unless defined?(@default_pen_name)
      @default_pen_name = HK::PenName.find(:first, :conditions => {:user_id => userid, :status_id => default_status_id })
    end
    if @default_pen_name
      return @default_pen_name.title
    else
      return nil
    end
  end
  
  def marketplace_pen_name
    marketplace_active_status_id = HK::Status.status_id('marketplace_active')
    unless defined?(@marketplace_pen_name)
      @marketplace_pen_name = HK::PenName.find(:first, :conditions => {:user_id => userid, :status_id => marketplace_active_status_id })
    end
    if @marketplace_pen_name == :undefined
      return nil
    elsif @marketplace_pen_name.nil?
      return nil
    else
      return @marketplace_pen_name.title
    end
  end
  
  def marketplace_pen_name_with_default
    marketplace_active_status_id = HK::Status.status_id('marketplace_active')
    unless defined?(@marketplace_pen_name)
      @marketplace_pen_name = HK::PenName.find(:first, :conditions => {:user_id => userid, :status_id => marketplace_active_status_id })
    end
    if @marketplace_pen_name
      if @marketplace_pen_name == :undefined
        return default_pen_name
      else
        return @marketplace_pen_name.title
      end
    else
      @marketplace_pen_name = :undefined
      return default_pen_name
    end
  end
  
  def project_manager
    ret_val = "Not Assigned"
    unless pmid.blank?
      pm = User.find_by_user_id(pmid)
      ret_val = pm.full_name
    end
    ret_val
  end
  
  def add_cust_admin(role, userid)
    u = User.find_user(userid)
    u.has_role!(role, self) if u
    !u.blank?
  end
  
  def delete_cust_admin(role, userid)
    u = User.find_user(userid)
    u.has_no_role!(role, self) if u
    !u.blank?
  end
  
  def customer_admins(type)
    raise("Customer.customer_admins ERROR: Invalid Type: '#{type}'") unless %w(task_creator sr_editor).include?(type)
    query = "select u.user_id from users u, 
    roles_users ru, roles r, customers c
    where r.name = '#{type}'
    and r.id = ru.role_id
    and u.id = ru.user_id
    and r.authorizable_id = c.id
    and r.authorizable_type = 'Customer'
    and c.id = #{id}"
    Customer.find_by_sql(query)
  end
  
  def self.admin_for(userid)
=begin    
    query = "select c.id, c.userid, c.is_active from customers c 
    where c.pmid = #{userid}
    union (
    select c.id, c.userid, c.is_active from customers c,
    roles_users ru, roles r, users u
    where r.name in ('task_creator', 'sr_editor')
    and r.authorizable_type = 'Customer'
    and r.authorizable_id = 1
    and ru.role_id = r.id
    and ru.user_id = u.id
    and u.user_id = #{userid})"
    Customer.find_by_sql(query)
=end
    query = %{
      ( 
        select 
          customers.id, 
          customers.userid, 
          customers.is_active 
        from 
          customers 
        where 
          customers.pmid = #{userid}
      )
      union
      (  
        select 
          customers.id, customers.userid, customers.is_active
        from 
          roles_users, users, roles, customers  
        where 
          roles_users.user_id = users.id 
          and users.user_id = #{userid} 
          and roles.id = roles_users.role_id 
          and authorizable_type = 'Customer' 
          and customers.id = roles.authorizable_id 
          and roles.name in ('project_manager','sr_editor')
      );

    }  
    return Customer.find_by_sql(query)
  end
  
  def init_with_defaults
    dc = Default.find_default_customer
    logger.debug("dc.writer_terms: " + dc.writer_terms)
    unless dc.blank?
      self.writer_terms = dc.writer_terms
      self.deliver_with_formatting = dc.deliver_with_formatting
      self.task_creator_payment = dc.task_creator_payment
      self.sr_editor_payment = dc.sr_editor_payment
      self.task_reviewer_payment = dc.task_reviewer_payment
      self.writer_payment = dc.writer_payment
      self.editor_payment = dc.editor_payment
      self.fact_checker_payment = dc.fact_checker_payment
      self.final_reviewer_payment = dc.final_reviewer_payment
      self.approver_payment = dc.approver_payment
      self.is_active = dc.is_active
    else
      raise("ERROR: Customer.init_from_defaults")
      logger.error("*** ERROR: Customer.init_from_defaults NOT able to get defaults")
    end
  end
  
  # OPTIMIZE: These same worker & group methods appear in Customer, Project & Task.  Move to module & mixin
  # returns [num_workers, is_group]
  def worker_count(role)
    ret = [0,false]
    return [1, false] if !self["#{role}_id"].blank?
    g = group(role)
    ret = [g.count_workers, true] unless g.blank?
    ret
  end
  
  # This finds the FIRST group
  def group(role)
    groups.find(:first, :conditions => {:worker_role => role})
  end
  
  # This deletes ALL groups (better clean up that finding only ONE matching group)
  def delete_group(role)
    old = groups.find(:all, :conditions => {:worker_role => role})
    groups.delete(old) unless old.blank?
  end

  def assign_worker(worker, role)
    logger.debug("*** Customer.assign_worker(#{worker.user_id}, #{role})")
    delete_group(role)
    self["#{role}_id"] = worker.user_id
    save!
  end

  def unassign_worker(role)
    logger.debug("*** Customer.unassign_worker(#{role})")
    # unassign the worker from the role for this object.
    self["#{role}_id"] = nil
    save!
  end


  # This deletes ALL groups (better clean up that finding only ONE matching group)
  def delete_group(role)
    old = groups.find(:all, :conditions => {:worker_role => role})
    old.each do |group|
      group.remove_workers(self)
    end
    groups.delete(old) unless old.blank?
  end
  
  private
  
  # Callback for update.  If the project manager id (pmid) has changed,
  # call User.find_user to ensure they are in the system and grant them the 
  # the project_manager role
  def update_pm
    if self.pmid_changed?
      old_u = User.find_user(self.pmid_was)
      old_u.has_no_role!('project_manager', self) unless old_u.nil?
      u = User.find_user(pmid)
      u.has_role!('project_manager', self) unless u.nil?
    end
  end
end
