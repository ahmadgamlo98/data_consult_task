<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class GenerateIdsCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'generate
                        {source : The path to the csv file (required)}
                        {--output= : The output file path (optional)}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Generate ids for given csv file.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        /** Get passed in arguments and options */
        $arguments  = $this->arguments();
        $options    = $this->options();

        /** Assets filenames */
        $emails_filename = './assets/emails.json';
        $filters_filename = './assets/filters.json';

        /** Read emails json and filters json files */
        if ( file_exists( $emails_filename ) && file_exists( $filters_filename ) ) {

            /** Get emails */
            $emails = json_decode( file_get_contents( $emails_filename ), true );

            /** Map emails to ids */
            $mapped_emails = [];

            foreach ( $emails as $email ) $mapped_emails[ $email['email'] ] = $email['_id'];

            /** Get filters */
            $filters = json_decode( file_get_contents( $filters_filename ), true );

            /** Initialize export data */
            $export_data = [];

            /** Get source */
            $source = $arguments['source'];

            /** Get output */
            $output = ! empty( $options['output'] ) ? $options['output'] : './export.json';

            /** Try to open the file */
            try {

                if ( ( $handle = fopen( $source, 'r' ) ) !== FALSE ) {

                    /** Initialize row index */
                    $row = 1;

                    /** Initialize csv headers */
                    $csv_headers = [];

                    /** Loop through csv file rows */
                    while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== FALSE ) {

                        /** Fix for the case where a csv file is semi-colon separated instead of comma separated */
                        $data = strpos( $data[0], ';' ) !== 0 ? explode( ';', $data[0] ) : $data;

                        /** Check if first row */
                        if ( $row === 1 ) {
                            $csv_headers = $data;
                        } else {

                            /** Initialize user id */
                            $user_id = '';

                            /** Initialize user attributes */
                            $user_attributes = [];

                            /** Loop through cells */
                            foreach ( $data as $column_name => $cell ) {

                                /** Get column name */
                                $csv_header_name = $csv_headers[ $column_name ];

                                /** Check if current cell is an email */
                                if ( $csv_header_name === 'email' ) {

                                    /** Get user id using email */
                                    $user_id = ! empty( $mapped_emails[ $cell ] ) ? $mapped_emails[ $cell ] : '';

                                    /** Check if a user id was not found */
                                    if ( empty( $user_id ) ) break;

                                } else {

                                    /** Loop through filters and check if column name equals one of the filter names */
                                    foreach ( $filters as $filter ) {

                                        /** Get filter names */
                                        $filter_names = $filter['name'];

                                        /** Check if the column name is equal to one of the names */
                                        if ( in_array( $csv_header_name, $filter_names ) ) {

                                            /** Get filter values */
                                            $filter_values = $filter['values'];

                                            /** Loop through filter values and check if cell value is equal to one of them */
                                            foreach ( $filter_values as $filter_value ) {

                                                /** Check cell value is equal to values */
                                                if ( in_array( $cell, $filter_value ) ) {

                                                    /** Add filter value id to user attributes */
                                                    $user_attributes[] = $filter_value['_id'];

                                                }

                                            }

                                        }

                                    }

                                }

                            }

                            /** Check user id */
                            if ( ! empty( $user_id ) ) {

                                /** Add user to export data */
                                $export_data[] = array(

                                    '_id'           =>  $user_id,
                                    'attributes'    =>  $user_attributes,

                                );

                            }

                        }

                        /** Increment row count */
                        $row++;

                    }

                    /** Close the file */
                    fclose($handle);

                    /** Save the exported data */
                    file_put_contents( $output, json_encode( $export_data ) );

                }

            } catch (\Throwable $th) {

                throw $th;
                /** Report file not found */
                $this->error( sprintf( 'File `%1$s` does not exist', $source ) );

            }

        } else {

            $this->error( 'Missing required json files `emails.json` `filters.json`' );

        }

    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}