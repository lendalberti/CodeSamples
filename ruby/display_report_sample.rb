 
  
  
  #----------------------------------------------------------
  #       Method: get_customer_list()
  #  Description: retrieves all available customer records
  #               from the database and collects them into 
  #               an array of hashes
  #       Author: ljd
  #   Updated on: Aug. 10, 2010
  # Input Params: none
  #       Return: array of of hashes of customer records,
  #               sorted by customer name in hash 
  #----------------------------------------------------------
  def get_customer_list
    customers         = []
    customers_sorted  = []
    total_payments    = 0
    all_customers     = Customer.all
    all_customer_ids  = all_customers.collect {|c| c.id}
    default_pen_names = Customer.default_pen_names(all_customers.collect {|c| c.helium_id})
    user_payments     = UserPayment.get_customer_total_payments_hash(all_customer_ids)
    projects_revenue  = Customer.sum_of_project_revenues(all_customer_ids)
  
    all_customers.each do |c|
      net_revenue    = 0
      gross_margin   = 0
      gross_revenue  = projects_revenue[c.id].to_i
      total_payments = user_payments[c.id].to_f
      net_revenue    = gross_revenue - total_payments
      gross_margin   = sprintf("%3.2f%", gross_revenue>0 ? net_revenue/gross_revenue*100 : 0)
      net_revenue    = currencify(net_revenue)
      gross_revenue  = currencify(gross_revenue)
      total_payments = currencify(total_payments)
    
      customers << {  :id             => c.id, 
                      :helium_id      => c.helium_id, 
                      :name           => default_pen_names[c.helium_id].to_s, 
                      :gross_revenue  => gross_revenue, 
                      :total_payments => total_payments, 
                      :net_revenue    => net_revenue, 
                      :gross_margin   => gross_margin }
                    
    end  
    return customers.sort_by { |c| c[:name] } 
  end
  
 
  #----------------------------------------------------------
  #       Method: show_project_list()
  #  Description: retrieves list of projects and relevant 
  #               info to be displayed by partial
  #       Author: ljd
  #   Updated on: Aug. 10, 2010
  # Input Params: none
  #       Return: renders 'show_project_list' partial
  #----------------------------------------------------------
  def show_project_list
     @projects = []
     tmp = []
     date_range = {}   
     date_range[:from], date_range[:to] = params[:range].split(',')    if !params[:range].nil?
     need_to_check_dates = ( date_range[:from] == "0" && date_range[:to] == "0" ? false : true )
     id = params[:id]
     c = Customer.find(id) 
     c.projects.each do |p|
        total_payments = 0
        p_start = Time.parse(p.start_date.to_s)
        project_start_date = p_start.to_i
        p_end = Time.parse(p.end_date.to_s)
        project_end_date = p_end.to_i
        if need_to_check_dates
          if (project_start_date < date_range[:from].to_i || project_start_date > date_range[:to].to_i) && 
             (project_end_date > date_range[:to].to_i || project_end_date < date_range[:from].to_i)
            next
          end
        end
        total_payments = get_project_total_payments(p.id)
        ptasks = p.tasks
        if !p.total_revenue.nil?
          net_revenue = p.total_revenue - total_payments      
          gross_margin = sprintf("%3.2f%", p.total_revenue>0 ? net_revenue/p.total_revenue*100 : 0)
          task_count = ptasks.size
        end
        delivered = true
        completed = true
        stateshash = TaskState.get_states_from_task_ids(ptasks.collect{|t| t.id})
        ptasks.each do |t|
          condition = t.state(true, stateshash[t.id])
          delivered = false  if !condition.match(/^delivered/i)
          completed = false  if !condition.match(/^completed/i)
        end
        if delivered
          condition = 'Delivered' 
        elsif completed
          condition = 'Completed'
        else
          condition = '(...)'
        end
        net_revenue = currencify(net_revenue)
        gross_revenue = currencify(p.total_revenue)
        total_payments = currencify(total_payments)
        @projects << { :name => p.name, :id => p.id, :tasks => task_count, :status => p.is_active ? "active" : "inactive", 
                       :condition => condition, :gross_revenue => gross_revenue, :total_payments => total_payments, 
                       :net_revenue => net_revenue, :gross_margin => gross_margin, :billing_info => p.billing_info,
                       :start_date => Time.at(project_start_date).strftime('%m/%d/%y'), :end_date => Time.at(project_end_date).strftime('%m/%d/%y') }
     end
     render :partial => 'show_project_list'
   end
   
   

   #----------------------------------------------------------
   #       Method: project_details()
   #  Description: retrieves info about a specific project
   #               like payment details, participants and
   #               corresponding roles, names, ids, and
   #               payments to be displayed by the partial
   #               project_details view
   #       Author: ljd
   #   Updated on: Aug. 10, 2010
   # Input Params: project id
   #       Return: renders 'project_details' partial
   #----------------------------------------------------------
   def project_details
     @participant = []
     project_payment_details = []

     id = $1  if params[:id] =~ /^project_id_(\d+)$/

     participant             = Project.find(id)
     project_payment_details = get_project_payment_details(id)

     ['task_reviewer', 'writer', 'fact_checker', 'editor',  'approver', 'final_reviewer'].each do |payee|
       worker_count, is_group =  participant.worker_count(payee)
       role    = payee.humanize.gsub(/^[a-z]|\s+[a-z]/) { |a| a.upcase }
       payment = currencify(project_payment_details[payee])

       if worker_count == 1 && !is_group
         full_name = User.find_user(participant[payee+'_id']).full_name
         id        = participant[payee+'_id']

         @participant << {   :role         => role, 
                             :full_name    => full_name, 
                             :id           => id, 
                             :payment      => payment }
       elsif  worker_count > 1
         @participant << {   :role         => role, 
                             :full_name    => "x#{worker_count}", 
                             :id           => '', 
                             :payment      => payment }
       else
         @participant << {   :role         => role, 
                             :full_name    => 'n/a', 
                             :id           => '', 
                             :payment      => payment }
       end
     end

     render :partial => 'project_details'
   end





  