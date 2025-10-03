import java.io.*;
import java.net.*;

public class ChatClient {
    public static void main(String[] args) {
        // Defining host and port
        String host = "localhost";
        int port = 5002;

        // Use the host and port provided
        if(args.length == 2) {
            host = args[0];
            port = Integer.parseInt(args[1]);
        }

        try {
            // Create client socket and connect to server
            Socket clientSocket = new Socket(host, port);
            System.out.println("Connected");


            // input and output streams to communicate with client handler on server
            BufferedReader serverIn = new BufferedReader(new InputStreamReader(clientSocket.getInputStream()));
            PrintWriter serverOut = new PrintWriter(clientSocket.getOutputStream(), true);

            // thread to print messages from server (so that we can read user input and print server messages)
            Thread t = new Thread(new Runnable() {
                public void run() {
                    String msg = "";
                    try {
                        while((msg = serverIn.readLine()) != null) { // as long as server is sending messages, print them
                            System.out.println(msg);
                        }
                    } catch(Exception e) {
                    }
                }
            });
            t.start();

            // main thread which reads user input and sends to client handler on server
            BufferedReader userIn = new BufferedReader(new InputStreamReader(System.in));
            String line = "";
            while((line = userIn.readLine()) != null) {
                serverOut.println(line); // send to the server
            }

        } catch(Exception e) {
            System.out.println("Something went wrong: " + e.getMessage());
        }
    }
}
