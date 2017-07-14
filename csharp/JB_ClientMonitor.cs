using System;
using System.Collections.Generic;
using System.Text;

using System.Management;
using System.Collections;
using System.Text.RegularExpressions;

using Microsoft.Data.Odbc;
using System.Runtime.InteropServices;

using System.Diagnostics;
using System.ServiceProcess;

using System.Threading;


public class JB_ClientMonitor : ServiceBase
{
    protected JB_Event jb = new JB_Event();
    public String joeboxIP = "";

    public static void Main()
    {
        ServiceBase.Run(new JB_ClientMonitor());
    }



    public JB_ClientMonitor()
    {
        CanPauseAndContinue = true;
        ServiceName = "Joebox Client Monitor";
    }

    public void JB_startMe()
    {
        try
        {
            string wqlQuery = String.Format(
               @"SELECT * FROM __InstanceCreationEvent WITHIN 2
                      WHERE TargetInstance         ISA 'Win32_NTLogEvent' AND
                            TargetInstance.Logfile   = 'Security'         AND
                            TargetInstance.EventCode = 540 ");

            WqlEventQuery query = new WqlEventQuery(wqlQuery);
            ManagementEventWatcher watcher = new ManagementEventWatcher(query);

            while (true)
            {
                ManagementBaseObject e = watcher.WaitForNextEvent();

                string msg = ((ManagementBaseObject)e["TargetInstance"])["Message"].ToString();
                Match u1 = Regex.Match(msg, @"User Name:\s+(\S+)\s+");
                string userName = Regex.Replace(u1.ToString(), @"^\s*.+\s+(\S+)\s+$", "$1");
                Match m = Regex.Match(msg, @"\d+\.\d+\.\d+\.\d+");
                string userIP = m.ToString();

                NetUserGroups ug = new NetUserGroups();
                string groupName = ug.getLocalGroupName(userName);

                if (userName.Length > 0 && groupName.Length > 0 && userIP.Length > 0)
                {
                    jb.logMsg("... logon by " + userName + " (member of " + groupName + ") on " + userIP);

                    MySQL_ODBC odbc = new MySQL_ODBC();
                    odbc.dbUpdate(joeboxIP, userName, groupName, userIP);
                }
                Thread.Sleep(1);

            }
        }
        catch (ManagementException e)
        {
            jb.logMsg("ERROR", e.Message);
        }
    }

    protected override void OnStart(string[] args)
    {
        if (args.Length == 0)
        {
            jb.logMsg("Joebox Client Monitor Service NOT started -- enter IP as start parameter.");
            throw new ArgumentNullException("Missing Start Service parameter");
        }
        else
        {
            joeboxIP = args[0];

            jb.logMsg("Joebox Client Monitor Service started for [" + joeboxIP + "]");
            Thread t = new Thread(new ThreadStart(this.JB_startMe));
            t.Start();
        }

    }
    protected override void OnStop()
    {
        jb.logMsg("Joebox Client Monitor Service stopped.");

    }
    protected override void OnPause()
    {
        jb.logMsg("Joebox Client Monitor Service paused.");

    }
    protected override void OnContinue()
    {
        jb.logMsg("Joebox Client Monitor Service continued.");
        Thread t = new Thread(new ThreadStart(this.JB_startMe));
        t.Start();
    }

    public class MySQL_ODBC
    {
        public void dbUpdate(string dbIP, string user, string group, string ip)
        {
            JB_Event jbdb = new JB_Event();

            try
            {
                OdbcCommand MyCommand = new OdbcCommand();

                //Connection string for Connector/ODBC 3.51
                string MyConString = "DRIVER={MySQL ODBC 3.51 Driver};" +
                                     "SERVER=" + dbIP + ";" +
                                     "DATABASE=ad;" +
                                     "UID=joebox;" +
                                     "PASSWORD=joebox;" +
                                     "OPTION=3";

                //Connect to MySQL using Connector/ODBC
                OdbcConnection MyConnection = new OdbcConnection(MyConString);
                MyConnection.Open();
                MyCommand.Connection = MyConnection;

                // get timestamp
                string ts = DateTime.Now.ToString("yyyy-MM-dd HH:mm:ss");

                // Delete existing record if any
                MyCommand.CommandText = "DELETE FROM ad_clients " +
                                        "WHERE user     = '" + user + "'" +
                                        "  AND ip       = '" + ip + "'" +
                                        "  AND domgroup = '" + group + "'";
                MyCommand.ExecuteNonQuery();

                //Insert
                MyCommand.CommandText = "INSERT INTO ad_clients VALUES(" +
                                        "'" + user + "','" + group + "','" + ip + "','" + ts + "')";
                MyCommand.ExecuteNonQuery();

                jbdb.logMsg("Joebox database updated at " + ts);

                //Close all resources
                MyConnection.Close();
            }

            catch (OdbcException MyOdbcException)
            {     //Catch any ODBC exception ...
                for (int i = 0; i < MyOdbcException.Errors.Count; i++)
                {
                    jbdb.logMsg("ERROR", "\nDatabase error " + i + "\n" +
                                  "Message: " + MyOdbcException.Errors[i].Message + "\n" +
                                  "Native: " + MyOdbcException.Errors[i].NativeError.ToString() + "\n" +
                                  "Source: " + MyOdbcException.Errors[i].Source + "\n" +
                                  "SQL: " + MyOdbcException.Errors[i].SQLState + "\n");
                }
            }
        }
    }

    public class JB_Event
    {
        private string sSource = "Joebox Client Monitor";
        private string sLog = "Application";

        public void logMsg(string error_flag, string msg)
        {
            if (!EventLog.SourceExists(sSource))
                EventLog.CreateEventSource(sSource, sLog);

            EventLog.WriteEntry(sSource, msg, EventLogEntryType.Error);
        }

        public void logMsg(string msg)
        {
            if (!EventLog.SourceExists(sSource))
                EventLog.CreateEventSource(sSource, sLog);

            EventLog.WriteEntry(sSource, msg, EventLogEntryType.Information);
        }
    }

    public class NetUserGroups
    {
        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
        public struct LOCALGROUP_USERS_INFO_0
        {
            public string groupname;
        }

        [DllImport("Netapi32.dll")]
        public extern static int NetUserGetLocalGroups([MarshalAs(UnmanagedType.LPWStr)]
            string servername, [MarshalAs(UnmanagedType.LPWStr)] string username, int level,
             int flags, out IntPtr bufptr, int prefmaxlen, out int entriesread, out int totalentries);

        [DllImport("Netapi32.dll")]
        extern static int NetApiBufferFree(IntPtr Buffer);


        public string getLocalGroupName(string userName)
        {
            int EntriesRead;
            int TotalEntries;
            IntPtr bufPtr;
            string groups = "";

            NetUserGetLocalGroups(null, userName, 0, 0, out bufPtr, 1024, out EntriesRead, out TotalEntries);

            if (EntriesRead > 0)
            {
                LOCALGROUP_USERS_INFO_0[] RetGroups = new LOCALGROUP_USERS_INFO_0[EntriesRead];
                IntPtr iter = bufPtr;
                for (int i = 0; i < EntriesRead; i++)
                {
                    RetGroups[i] = (LOCALGROUP_USERS_INFO_0)Marshal.PtrToStructure(iter, typeof(LOCALGROUP_USERS_INFO_0));
                    iter = (IntPtr)((int)iter + Marshal.SizeOf(typeof(LOCALGROUP_USERS_INFO_0)));
                    groups += (groups.Length == 0 ? "" : ", ") + RetGroups[i].groupname;
                }
                NetApiBufferFree(bufPtr);
                return (groups);
            }
            return ("");
        }
    }

}